@php
    $select = 'mt-1 block w-full min-h-11 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-base focus:border-indigo-500 focus:ring-indigo-500';
    $placeholders = ['user' => 'name@'.($domain ?: 'example.org'), 'group' => 'teachers@'.($domain ?: 'example.org'), 'ou' => '/Staff'];
@endphp

<x-admin-layout :title="__('Users and roles')">
    @include('access::tabs')

    @if ($errors->has('sync'))
        <p class="rounded-md bg-red-50 dark:bg-red-900/30 px-4 py-3 text-sm text-red-800 dark:text-red-300">{{ $errors->first('sync') }}</p>
    @endif

    @unless ($gamReady)
        <div class="rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 text-sm text-amber-900 dark:text-amber-200">
            <p>{{ __('Google access reads Google through GAM, which isn\'t set up yet.') }}</p>
        </div>
    @endunless

    <x-settings-card :title="__('Roles from Google')">
        @if (Route::has('docs.show'))
            <p><a href="{{ route('docs.show', 'google-access') }}" class="font-medium text-indigo-600 dark:text-indigo-400 underline">{{ __('How to set up Google access') }}</a></p>
        @endif
        @if ($rules->isNotEmpty())
            <ul class="divide-y divide-gray-200 dark:divide-gray-700 rounded-md border border-gray-200 dark:border-gray-700">
                @foreach ($rules as $rule)
                    <li class="flex flex-col gap-1 px-3 py-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $rule->role->name }}</p>
                            <p class="break-all text-gray-600 dark:text-gray-400">
                                {{ __(\TornadoEngine\AccessGoogle\Models\Rule::TYPES[$rule->type]) }}: <code class="font-mono">{{ $rule->value }}</code>@if ($rule->type === 'ou' && $rule->sub_ous) {{ __('and sub-OUs') }}@endif
                            </p>
                            @if ($rule->matched !== null)
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ trans_choice(':count person at the last sync|:count people at the last sync', $rule->matched) }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('access.google.rules.destroy', $rule) }}" class="-ms-2 sm:ms-0">
                            @csrf
                            @method('DELETE')
                            <button class="min-h-11 px-2 text-sm text-red-700 dark:text-red-400 underline">{{ __('Remove') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($roles->isEmpty())
            <p>{!! __('Make a role on the :roles tab first.', ['roles' => '<a href="'.route('access.roles.index').'" class="font-medium text-indigo-600 dark:text-indigo-400 underline">'.e(__('Roles')).'</a>']) !!}</p>
        @else
            <form method="POST" action="{{ route('access.google.rules.store') }}" class="space-y-3" x-data="{ type: @js(old('type', 'group')) }">
                @csrf
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <x-input-label for="role_id" :value="__('Role')" />
                        <select id="role_id" name="role_id" class="{{ $select }}" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected((int) old('role_id') === $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="type" :value="__('Given to')" />
                        <select id="type" name="type" x-model="type" class="{{ $select }}">
                            @foreach (\TornadoEngine\AccessGoogle\Models\Rule::TYPES as $type => $label)
                                <option value="{{ $type }}">{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="value" x-text="{ user: @js(__('Email address')), group: @js(__('Group address')), ou: @js(__('OU path')) }[type]">{{ __('Value') }}</x-input-label>
                        <x-text-input id="value" name="value" class="mt-1 block w-full" type="text" :value="old('value')" required autocapitalize="off" spellcheck="false"
                                      x-bind:placeholder="{{ Js::from($placeholders) }}[type]" />
                    </div>
                </div>
                <x-input-error :messages="$errors->get('value')" />
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label x-show="type === 'ou'" class="inline-flex min-h-11 items-center gap-2">
                        <input type="hidden" name="sub_ous" value="0">
                        <input type="checkbox" name="sub_ous" value="1" checked class="rounded text-indigo-600 border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:ring-indigo-500">
                        <span>{{ __('Include sub-OUs') }}</span>
                    </label>
                    <x-primary-button class="ms-auto">{{ __('Add rule') }}</x-primary-button>
                </div>
            </form>
        @endif
    </x-settings-card>

    <x-settings-card :title="__('Sync')">
        <p>
            @if ($settings->last_run_at)
                {{ __('Last sync :time:', ['time' => $settings->last_run_at->diffForHumans()]) }}
                <span @class(['font-medium', 'text-green-700 dark:text-green-400' => $settings->last_status === 'ok', 'text-red-700 dark:text-red-400' => $settings->last_status !== 'ok'])>{{ $settings->last_status === 'ok' ? __('worked') : __('failed') }}</span>
                @if ($settings->last_message) — {{ $settings->last_message }}@endif
            @else
                {{ __('Hasn\'t synced yet.') }}
            @endif
        </p>
        <p class="text-gray-600 dark:text-gray-400">
            {{ $settings->enabled ? __('Runs on its own: :when', ['when' => \TornadoEngine\Cron\Explain::describe($schedule)]) : __('The nightly sync is turned off.') }}
            @can('administration')
                <a href="{{ route('administration.tab', 'cron') }}" class="font-medium text-indigo-600 dark:text-indigo-400 underline">{{ __('Change on Scheduled jobs') }}</a>
            @endcan
        </p>

        <form method="POST" action="{{ route('access.google.sync') }}" class="flex justify-end">
            @csrf
            <x-primary-button>{{ __('Sync now') }}</x-primary-button>
        </form>
    </x-settings-card>
</x-admin-layout>
