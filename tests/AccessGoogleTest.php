<?php

namespace TornadoEngine\AccessGoogle\Tests;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use TornadoEngine\Access\Models\Role;
use TornadoEngine\AccessGoogle\Models\Rule;
use TornadoEngine\Cron\Cron;
use TornadoEngine\Cron\Models\CronJob;
use TornadoEngine\Gam\Gam;
use TornadoEngine\Gam\GamException;

class AccessGoogleTest extends TestCase
{
    use RefreshDatabase;

    /** Everyone active in the made-up Google Workspace. */
    private array $people = [
        ['email' => 'ann@school.test', 'name' => 'Ann Teacher', 'ou' => '/Staff/HS'],
        ['email' => 'bob@school.test', 'name' => 'Bob Office', 'ou' => '/Staff/Office'],
        ['email' => 'cy@school.test', 'name' => 'Cy Tech', 'ou' => '/Tech'],
        ['email' => 'wm30kid@school.test', 'name' => 'Kid Student', 'ou' => '/Students/HS'],
    ];

    private array $groups = ['teachers@school.test' => ['ann@school.test', 'wm30kid@school.test', 'someone@elsewhere.test']];

    private bool $gamFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('google.domain', 'school.test');

        $test = $this;
        $this->app->instance(Gam::class, new class($test) extends Gam
        {
            public function __construct(private $test)
            {
                parent::__construct('http://gam', 'token');
            }

            public function forJob(): static
            {
                return $this;
            }

            public function usersIn(string $orgUnit, bool $subOrgUnits = true): array
            {
                return $this->test->gam('people');
            }

            public function groupMembers(string $group): array
            {
                return $this->test->gam('group', $group);
            }

            public function run(array $args, ?int $timeout = null, ?int $wait = null): array
            {
                return $this->test->gam('run', $args);
            }
        });
    }

    public function gam(string $what, mixed $arg = null): array
    {
        if ($this->gamFails) {
            throw new GamException('GAM is busy with other commands. Try again in a moment.');
        }

        return match ($what) {
            'people' => $this->people,
            'group' => $this->groups[$arg] ?? throw new GamException('Does not exist'),
            'run' => ['exit' => str_contains(implode(' ', $arg), 'missing') ? 1 : 0, 'stdout' => '', 'stderr' => ''],
        };
    }

    private function sync(): ?bool
    {
        return app(Cron::class)->run(app(Cron::class)->find('access-google.sync'));
    }

    private function rolesOf(string $email): array
    {
        return User::where('email', $email)->first()?->roles()->orderBy('name')->pluck('name')->all() ?? [];
    }

    private function manager(): User
    {
        return User::factory()->create(['is_admin' => true, 'email' => 'it@school.test']);
    }

    public function test_roles_are_given_by_group_ou_and_user_with_accounts_made_for_new_people(): void
    {
        $teachers = Role::create(['name' => 'Teachers']);
        $office = Role::create(['name' => 'Office']);
        $tech = Role::create(['name' => 'Tech']);
        Rule::create(['role_id' => $teachers->id, 'type' => 'group', 'value' => 'teachers@school.test']);
        Rule::create(['role_id' => $office->id, 'type' => 'ou', 'value' => '/Staff', 'sub_ous' => true]);
        Rule::create(['role_id' => $tech->id, 'type' => 'user', 'value' => 'cy@school.test']);

        $this->assertTrue($this->sync());

        $this->assertSame(['Office', 'Teachers'], $this->rolesOf('ann@school.test'));
        $this->assertSame(['Office'], $this->rolesOf('bob@school.test'));
        $this->assertSame(['Tech'], $this->rolesOf('cy@school.test'));
        $this->assertSame('Bob Office', User::where('email', 'bob@school.test')->value('name'));
        $this->assertNull(User::where('email', 'someone@elsewhere.test')->first(), 'outside addresses get nothing');
        $this->assertSame(2, Rule::where('type', 'ou')->value('matched'));
        $this->assertSame('4 people matched. Gave 5 roles, took back 0 roles. Made 4 new accounts.', CronJob::find('access-google.sync')->last_message);
    }

    public function test_people_who_leave_lose_the_role_but_roles_given_by_hand_stay(): void
    {
        $teachers = Role::create(['name' => 'Teachers']);
        $byHand = Role::create(['name' => 'Library']);
        Rule::create(['role_id' => $teachers->id, 'type' => 'group', 'value' => 'teachers@school.test']);
        $ann = User::factory()->create(['email' => 'ann@school.test']);
        $ann->roles()->attach($byHand);
        $this->sync();
        $this->assertSame(['Library', 'Teachers'], $this->rolesOf('ann@school.test'));

        // Ann leaves the group; Bob was given Teachers by hand and isn't in it.
        $this->groups['teachers@school.test'] = [];
        $bob = User::factory()->create(['email' => 'bob@school.test']);
        $bob->roles()->attach($teachers);
        $this->sync();

        $this->assertSame(['Library'], $this->rolesOf('ann@school.test'));
        $this->assertSame(['Teachers'], $this->rolesOf('bob@school.test'));
    }

    public function test_suspended_people_lose_the_role(): void
    {
        $office = Role::create(['name' => 'Office']);
        Rule::create(['role_id' => $office->id, 'type' => 'user', 'value' => 'bob@school.test']);
        $this->sync();
        $this->assertSame(['Office'], $this->rolesOf('bob@school.test'));

        // GAM leaves out suspended accounts.
        $this->people = array_values(array_filter($this->people, fn ($p) => $p['email'] !== 'bob@school.test'));
        $this->sync();
        $this->assertSame([], $this->rolesOf('bob@school.test'));
    }

    public function test_nothing_changes_when_gam_fails(): void
    {
        $teachers = Role::create(['name' => 'Teachers']);
        Rule::create(['role_id' => $teachers->id, 'type' => 'group', 'value' => 'teachers@school.test']);
        $this->sync();

        $this->gamFails = true;
        $this->assertFalse($this->sync());
        $this->assertSame(['Teachers'], $this->rolesOf('ann@school.test'));

        $this->actingAs($this->manager())->from('/access/google')->post('/access/google/sync')
            ->assertSessionHasErrors(['sync' => 'The sync failed, so nothing changed: GAM is busy with other commands. Try again in a moment.']);
    }

    public function test_rules_are_checked_with_google_before_theyre_added(): void
    {
        $role = Role::create(['name' => 'Teachers']);
        $manager = $this->manager();

        $this->actingAs($manager)->from('/access/google')->post('/access/google/rules', ['role_id' => $role->id, 'type' => 'group', 'value' => 'missing@school.test'])
            ->assertSessionHasErrors(['value' => 'Google doesn\'t have Google Group missing@school.test.']);
        $this->actingAs($manager)->post('/access/google/rules', ['role_id' => $role->id, 'type' => 'user', 'value' => 'not an email'])
            ->assertSessionHasErrors('value');
        $this->actingAs($manager)->post('/access/google/rules', ['role_id' => $role->id, 'type' => 'ou', 'value' => 'Staff/', 'sub_ous' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertSame('/Staff', Rule::sole()->value);
        $this->actingAs($manager)->get('/access/google')
            ->assertOk()
            ->assertSee('Roles from Google')
            ->assertSee('/Staff');
    }

    public function test_only_people_who_manage_access_can_open_it(): void
    {
        $this->actingAs(User::factory()->create())->get('/access/google')->assertForbidden();
        $this->actingAs($this->manager())->get('/access/users')->assertSee('Google');
    }
}
