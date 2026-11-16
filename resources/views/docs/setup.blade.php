@php
    $code = 'rounded bg-gray-100 dark:bg-gray-900 px-1 py-0.5 font-mono text-[13px] text-gray-900 dark:text-gray-100';
    $link = 'font-medium text-indigo-600 dark:text-indigo-400 underline';
@endphp

<x-setup.header :title="__('Set up Google access')" />

<div class="mt-6 px-6 sm:px-8 pb-6 sm:pb-8 space-y-6 text-sm text-gray-700 dark:text-gray-300">
    <section class="space-y-2">
        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('1. Set up GAM') }}</h2>
        <p>{!! __('Google access reads your users, groups and OUs through GAM. Follow :link, then click Check connection on the GAM card in Administration.', ['link' => Route::has('docs.show') ? '<a href="'.route('docs.show', 'gam').'" class="'.$link.'">'.e(__('Set up GAM')).'</a>' : e(__('Set up GAM'))]) !!}</p>
    </section>

    <section class="space-y-2">
        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('2. Make roles') }}</h2>
        <p>{{ __('In Users and roles → Roles, make a role for each group of people, and choose what it can open.') }}</p>
    </section>

    <section class="space-y-2">
        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('3. Add rules') }}</h2>
        <p>{{ __('In Users and roles → Google, give each role to:') }}</p>
        <ul class="list-disc ps-5 space-y-1">
            <li>{!! __('a Google user, by email address;') !!}</li>
            <li>{!! __('a Google Group, by its address, for example :example (members of groups inside it count);', ['example' => '<code class="'.$code.'">teachers@district.org</code>']) !!}</li>
            <li>{!! __('or an OU, by its path, for example :example, with or without its sub-OUs.', ['example' => '<code class="'.$code.'">/Staff</code>']) !!}</li>
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="font-semibold text-gray-900 dark:text-gray-100">{{ __('4. Sync') }}</h2>
        <p>{{ __('Click Sync now, or wait for the nightly sync. People who match get an account and the role; people who leave the group or OU, or are suspended, lose it. To leave someone out, exclude them on the Excluded tab.') }}</p>
    </section>
</div>
