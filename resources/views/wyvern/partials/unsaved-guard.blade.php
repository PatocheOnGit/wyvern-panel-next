{{-- Warns before leaving a form with unsaved edits: closing the tab, or navigating in the panel. --}}
<div x-data="{ dirty: false }"
     x-init="$nextTick(() => $wire.$watch('data', () => dirty = true))"
     x-on:wyvern-form-saved.window="dirty = false"
     x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }"
     x-on:livewire:navigate.window="if (dirty && ! confirm(@js(trans('wyvern.properties.unsaved_confirm')))) { $event.preventDefault() }">
    <p class="wy-unsaved" x-show="dirty" x-cloak>
        <x-filament::icon icon="tabler-point-filled" class="h-4 w-4" />
        {{ trans('wyvern.properties.unsaved') }}
    </p>
</div>
