@php
    $databases = $this->availableDatabases();
@endphp

<x-filament-panels::page>
    @if ($databases->isEmpty())
        {{-- An empty state that says where databases come from. Arriving here with none is
             the normal first experience, and "no options" in a dropdown explains nothing. --}}
        <x-filament::section>
            <x-slot name="heading">{{ trans('wyvern.database_access.empty.heading') }}</x-slot>
            <x-slot name="description">{{ trans('wyvern.database_access.empty.body') }}</x-slot>
        </x-filament::section>
    @else
        <form wire:submit="open" class="wy-stack">
            <x-filament::section>
                <x-slot name="heading">{{ trans('wyvern.database_access.heading') }}</x-slot>
                <x-slot name="description">{{ trans('wyvern.database_access.description') }}</x-slot>

                {{ $this->form }}

                <x-slot name="footerActions">
                    {{ $this->openAction }}
                </x-slot>
            </x-filament::section>
        </form>
    @endif
</x-filament-panels::page>
