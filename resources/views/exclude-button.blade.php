{{-- Next to Edit on the Users list, for people the Google sync added. --}}
@if (\TornadoEngine\AccessGoogle\Sync::imported($user))
    <div x-data="{ open: false }" x-on:keydown.escape="open = false" class="relative">
        <x-secondary-button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open">{{ __('Exclude') }}</x-secondary-button>
        <div x-show="open" x-cloak x-on:click.outside="open = false"
             class="absolute start-0 z-10 mt-2 w-72 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 text-sm shadow-lg sm:start-auto sm:end-0">
            @include('access-google::exclude-form', ['email' => $user->email])
        </div>
    </div>
@endif
