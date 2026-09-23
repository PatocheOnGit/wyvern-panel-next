@php
    $managed = $this->managed();
@endphp

<x-filament-panels::page>
    @if (! $this->exists)
        <div class="wy-page-note">
            <x-filament::icon icon="tabler-info-circle" class="h-5 w-5" />
            <p>{{ trans('wyvern.properties.missing') }}</p>
        </div>
    @else
        @if (count($this->keys) < 12)
            <div class="wy-page-note">
                <x-filament::icon icon="tabler-info-circle" class="h-5 w-5" />
                <p>{{ trans('wyvern.properties.sparse') }}</p>
            </div>
        @endif

        @unless ($this->canEdit())
            <div class="wy-page-note">
                <x-filament::icon icon="tabler-lock" class="h-5 w-5" />
                <p>{{ trans('wyvern.properties.read_only') }}</p>
            </div>
        @endunless

        <div class="wy-content-bar">
            <div class="wy-content-search">
                <x-filament::icon icon="tabler-search" class="h-4 w-4" />
                <input type="text" wire:model.live.debounce.300ms="filter" placeholder="{{ trans('wyvern.properties.filter') }}">
            </div>
            @include('wyvern.partials.unsaved-guard')
        </div>

        {{ $this->form }}

        @if ($managed !== [])
            <p class="wy-page-footnote">
                {{ trans('wyvern.properties.managed') }}
                @foreach ($managed as $key => $value)
                    <code>{{ $key }}={{ $value }}</code>
                @endforeach
            </p>
        @endif

        <div class="wy-page-actions">
            {{ $this->saveAction }}
        </div>
    @endif
</x-filament-panels::page>
