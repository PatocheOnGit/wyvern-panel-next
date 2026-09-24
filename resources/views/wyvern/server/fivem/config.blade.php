@php
    $layout = $this->layout();
    $fivem = $this->fivem();
@endphp

<x-filament-panels::page>
    @if ($layout->pending)
        <div class="wy-page-note">
            <x-filament::icon icon="tabler-info-circle" class="h-5 w-5" />
            <p>{{ trans('wyvern.fivem.txadmin.pending') }}</p>
        </div>
    @elseif ($fivem->usesTxAdmin())
        <div class="wy-page-note">
            <x-filament::icon icon="tabler-info-circle" class="h-5 w-5" />
            <p>{{ trans('wyvern.fivem.txadmin.deployment', ['path' => $layout->cfg]) }}</p>
        </div>
    @elseif ($fivem->enhanced())
        <div class="wy-page-note">
            <x-filament::icon icon="tabler-info-circle" class="h-5 w-5" />
            <p>{{ trans('wyvern.fivem.config.enhanced_note') }}</p>
        </div>
    @endif

    @if (! $this->exists && ! $layout->pending)
        <div class="wy-page-note">
            <x-filament::icon icon="tabler-info-circle" class="h-5 w-5" />
            <p>{{ trans('wyvern.fivem.config.missing') }}</p>
        </div>
    @elseif ($this->exists)
        @unless ($this->canEdit())
            <div class="wy-page-note">
                <x-filament::icon icon="tabler-lock" class="h-5 w-5" />
                <p>{{ trans('wyvern.properties.read_only') }}</p>
            </div>
        @endunless

        @include('wyvern.partials.unsaved-guard')

        {{ $this->form }}

        <p class="wy-page-footnote">{{ trans($fivem->usesTxAdmin() ? 'wyvern.fivem.config.managed_txadmin' : 'wyvern.fivem.config.managed') }}</p>

        <div class="wy-page-actions">
            {{ $this->saveAction }}
        </div>
    @endif
</x-filament-panels::page>
