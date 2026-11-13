<?php

namespace TornadoEngine\AccessGoogle;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use TornadoEngine\Access\Access;
use TornadoEngine\AccessGoogle\Models\Exclusion;
use TornadoEngine\AccessGoogle\Models\Rule;
use TornadoEngine\Gam\Gam;

/**
 * Gives each rule's role to the people who match it, and takes it back from people who no longer
 * do. Only roles the sync gave are taken back. Everything is read from Google before anything
 * changes, so if GAM fails, nothing changes.
 */
class Sync
{
    public function __construct(private Gam $gam, private Access $access) {}

    /**
     * Takes back the roles the sync gave this person (roles given by hand stay).
     */
    public static function takeBack(User $user): int
    {
        return DB::transaction(function () use ($user) {
            $roles = DB::table('access_google_grants')->where('user_id', $user->id)->pluck('role_id');
            DB::table('access_role_user')->where('user_id', $user->id)->whereIn('role_id', $roles)->delete();
            DB::table('access_google_grants')->where('user_id', $user->id)->delete();

            return $roles->count();
        });
    }

    /** Whether the sync made or gave roles to this person. */
    public static function imported(User $user): bool
    {
        return DB::table('access_google_grants')->where('user_id', $user->id)->exists();
    }

    /**
     * @return string A summary, for example "312 people matched. Gave 4 roles, took back 1."
     */
    public function run(): string
    {
        $rules = Rule::all();
        $gam = $this->gam->forJob();

        // Everyone active in Google, by address: suspended accounts and outside addresses match nothing.
        $people = [];
        foreach ($rules->isEmpty() ? [] : $gam->usersIn('/') as $person) {
            $people[$person['email']] = $person;
        }

        $excluded = array_fill_keys(Exclusion::pluck('email')->all(), true);

        // Who should have each role: [role id => [email => true]].
        $wanted = [];
        $matched = [];
        foreach ($rules as $rule) {
            $emails = match ($rule->type) {
                'user' => [strtolower($rule->value)],
                'group' => $gam->groupMembers($rule->value),
                'ou' => array_keys(array_filter($people, fn ($person) => $rule->coversOu($person['ou']))),
            };
            $emails = array_filter($emails, fn ($email) => isset($people[$email]) && ! isset($excluded[$email]));

            $matched[$rule->id] = count($emails);
            foreach ($emails as $email) {
                $wanted[$rule->role_id][$email] = true;
            }
        }

        $summary = DB::transaction(fn () => $this->apply($wanted, $people, $matched));
        $this->access->forget();

        return $summary;
    }

    /**
     * @param  array<int, array<string, true>>  $wanted
     * @param  array<string, array{email: string, name: string, ou: string}>  $people
     * @param  array<int, int>  $matched
     */
    private function apply(array $wanted, array $people, array $matched): string
    {
        $emails = array_keys(array_merge(...array_values($wanted ?: [[]])));

        // People who match a rule need an account to sign in with.
        $ids = User::whereIn('email', $emails)->pluck('id', 'email')->all();
        $added = 0;
        foreach (array_diff($emails, array_keys($ids)) as $email) {
            $user = new User;
            $user->forceFill(['email' => $email, 'name' => $people[$email]['name'] ?: strstr($email, '@', true)])->save();
            $ids[$email] = $user->id;
            $added++;
        }

        $key = fn ($row) => $row->role_id.':'.$row->user_id;
        $held = DB::table('access_role_user')->get()->keyBy($key);
        $granted = DB::table('access_google_grants')->get()->keyBy($key);

        $want = [];
        foreach ($wanted as $roleId => $members) {
            foreach (array_keys($members) as $email) {
                $want[$roleId.':'.$ids[$email]] = ['role_id' => $roleId, 'user_id' => $ids[$email]];
            }
        }

        $gave = 0;
        foreach ($want as $pair => $row) {
            if (! $held->has($pair)) {
                DB::table('access_role_user')->insert($row);
                DB::table('access_google_grants')->insert($row);
                $gave++;
            }
        }

        $tookBack = 0;
        foreach ($granted as $pair => $row) {
            if (! isset($want[$pair])) {
                DB::table('access_role_user')->where(['role_id' => $row->role_id, 'user_id' => $row->user_id])->delete();
                DB::table('access_google_grants')->where(['role_id' => $row->role_id, 'user_id' => $row->user_id])->delete();
                $tookBack++;
            }
        }

        foreach ($matched as $ruleId => $count) {
            Rule::whereKey($ruleId)->update(['matched' => $count]);
        }

        return trans_choice(':count person matched.|:count people matched.', count($emails))
            .' '.__('Gave :gave, took back :back.', [
                'gave' => trans_choice(':count role|:count roles', $gave),
                'back' => trans_choice(':count role|:count roles', $tookBack),
            ])
            .($added ? ' '.trans_choice('Made :count new account.|Made :count new accounts.', $added) : '');
    }
}
