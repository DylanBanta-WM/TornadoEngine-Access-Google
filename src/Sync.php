<?php

namespace TornadoEngine\AccessGoogle;

use TornadoEngine\Access\Imports;
use TornadoEngine\AccessGoogle\Models\Rule;
use TornadoEngine\Gam\Gam;

/**
 * Gives each rule's role to the people who match it, and takes it back from people who no longer
 * do (through Access's Imports: only roles this sync gave are taken back, and excluded people are
 * left out). Everything is read from Google before anything changes, so if GAM fails, nothing changes.
 */
class Sync
{
    public function __construct(private Gam $gam, private Imports $imports) {}

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

        $excluded = $this->imports->excluded();

        // Who should have each role: [role id => [email => name]].
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
                $wanted[$rule->role_id][$email] = $people[$email]['name'];
            }
        }

        $result = $this->imports->apply('google', $wanted);

        foreach ($matched as $ruleId => $count) {
            Rule::whereKey($ruleId)->update(['matched' => $count]);
        }

        return trans_choice(':count person matched.|:count people matched.', $result['people'])
            .' '.__('Gave :gave, took back :back.', [
                'gave' => trans_choice(':count role|:count roles', $result['gave']),
                'back' => trans_choice(':count role|:count roles', $result['took_back']),
            ])
            .($result['added'] ? ' '.trans_choice('Made :count new account.|Made :count new accounts.', $result['added']) : '');
    }
}
