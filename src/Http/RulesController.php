<?php

namespace TornadoEngine\AccessGoogle\Http;

use App\Models\User;
use App\Support\GoogleSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\View\View;
use Throwable;
use TornadoEngine\Access\Access;
use TornadoEngine\Access\Models\Role;
use TornadoEngine\AccessGoogle\AccessGoogleServiceProvider;
use TornadoEngine\AccessGoogle\Models\Exclusion;
use TornadoEngine\AccessGoogle\Models\Rule;
use TornadoEngine\AccessGoogle\Sync;
use TornadoEngine\Cron\Cron;
use TornadoEngine\Gam\Gam;

class RulesController extends Controller
{
    public function index(Cron $cron): View
    {
        $job = $cron->find(AccessGoogleServiceProvider::JOB);

        return view('access-google::index', [
            'rules' => Rule::with('role')->get()->sortBy(fn (Rule $rule) => [$rule->role->name, $rule->type, $rule->value]),
            'roles' => Role::orderBy('name')->get(),
            'job' => $job,
            'settings' => $cron->settings($job),
            'schedule' => $cron->scheduleFor($job),
            'domain' => GoogleSignIn::domain(),
            'exclusions' => Exclusion::with('excludedBy')->orderBy('email')->get(),
        ]);
    }

    public function store(Request $request, Gam $gam): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer', ValidationRule::exists('access_roles', 'id')],
            'type' => ['required', ValidationRule::in(array_keys(Rule::TYPES))],
            'value' => ['required', 'string', 'max:255'],
            'sub_ous' => ['boolean'],
        ], [], ['role_id' => __('role'), 'value' => __('value')]);

        $value = trim($data['value']);
        if ($data['type'] === 'ou') {
            $value = '/'.trim($value, '/');
        } else {
            $value = strtolower($value);
            if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return back()->withErrors(['value' => __('Enter an email address.')])->withInput();
            }
        }

        // Check it exists now, so a typo doesn't make every nightly sync fail.
        $lookup = ['user' => ['info', 'user', $value, 'quick'], 'group' => ['info', 'group', $value, 'nousers'], 'ou' => ['info', 'org', $value, 'nousers']][$data['type']];
        try {
            $found = $gam->run($lookup)['exit'] === 0;
        } catch (Throwable $e) {
            return back()->withErrors(['value' => $e->getMessage()])->withInput();
        }
        if (! $found) {
            return back()->withErrors(['value' => __('Google doesn\'t have :thing :value.', ['thing' => __(Rule::TYPES[$data['type']]), 'value' => $value])])->withInput();
        }

        Rule::firstOrCreate(
            ['role_id' => $data['role_id'], 'type' => $data['type'], 'value' => $value],
            ['sub_ous' => $data['type'] === 'ou' && $request->boolean('sub_ous')],
        );

        return back()->with('status', __('Added. People get the role at the next sync, or click Sync now.'));
    }

    public function destroy(Rule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('status', __('Removed. People who only had the role from this rule lose it at the next sync, or click Sync now.'));
    }

    /**
     * Leaves someone out of the sync, and takes back the roles it gave them.
     */
    public function exclude(Request $request, Access $access): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['required', 'string', 'max:255'],
            'other' => ['nullable', 'required_if:reason,other', 'string', 'max:255'],
        ], [], ['other' => __('reason')]);

        $email = strtolower(trim($data['email']));
        $reason = $data['reason'] === 'other' ? trim($data['other']) : $data['reason'];

        Exclusion::updateOrCreate(['email' => $email], ['reason' => $reason, 'excluded_by' => $request->user()->id]);

        if ($user = User::where('email', $email)->first()) {
            Sync::takeBack($user);
            $access->forget();
        }

        return back()->with('status', __(':email is excluded from Google access.', ['email' => $email]));
    }

    public function include(Exclusion $exclusion): RedirectResponse
    {
        $exclusion->delete();

        return back()->with('status', __(':email will be included again at the next sync.', ['email' => $exclusion->email]));
    }

    public function sync(Cron $cron): RedirectResponse
    {
        $job = $cron->find(AccessGoogleServiceProvider::JOB);
        $ok = $cron->run($job, wait: false);

        if ($ok === null) {
            return back()->withErrors(['sync' => __('Another scheduled job is running. Jobs run one at a time, so try again when it finishes.')]);
        }

        $message = $cron->settings($job)->last_message;

        return $ok
            ? back()->with('status', __('Synced. :summary', ['summary' => $message]))
            : back()->withErrors(['sync' => __('The sync failed, so nothing changed: :reason', ['reason' => $message])]);
    }
}
