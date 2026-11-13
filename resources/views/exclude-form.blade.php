{{-- Exclude someone from the sync. $email is set when it's for a known person. --}}
<form method="POST" action="{{ route('access.google.exclusions.store') }}" class="space-y-3" x-data="{ reason: @js(\TornadoEngine\AccessGoogle\Models\Exclusion::REASONS[0]) }">
    @csrf
    @if (isset($email))
        <input type="hidden" name="email" value="{{ $email }}">
    @else
        <div>
            <x-input-label for="exclude-email" :value="__('Email')" />
            <x-text-input id="exclude-email" name="email" type="email" class="mt-1 block w-full" required autocapitalize="off" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
    @endif
    <div>
        <x-input-label :for="'exclude-reason-'.($email ?? 'new')" :value="__('Reason')" />
        <select id="exclude-reason-{{ $email ?? 'new' }}" name="reason" x-model="reason" class="mt-1 block w-full min-h-11 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-base focus:border-indigo-500 focus:ring-indigo-500">
            @foreach (\TornadoEngine\AccessGoogle\Models\Exclusion::REASONS as $reason)
                <option value="{{ $reason }}">{{ __($reason) }}</option>
            @endforeach
            <option value="other">{{ __('Other') }}</option>
        </select>
    </div>
    <div x-show="reason === 'other'" x-cloak>
        <x-input-label :for="'exclude-other-'.($email ?? 'new')" :value="__('Other reason')" />
        <x-text-input :id="'exclude-other-'.($email ?? 'new')" name="other" type="text" class="mt-1 block w-full" x-bind:required="reason === 'other'" />
        <x-input-error :messages="$errors->get('other')" class="mt-2" />
    </div>
    <div class="flex justify-end">
        <x-danger-button>{{ __('Exclude') }}</x-danger-button>
    </div>
</form>
