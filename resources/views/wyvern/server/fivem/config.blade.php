<x-filament-panels::page>
    @if ($this->fivem()->usesTxAdmin())
        <div class="wy-page-note">
            <x-filament::icon icon="tabler-info-circle" class="h-5 w-5" />
            <p>{{ trans('wyvern.fivem.config.txadmin_note') }}</p>
        </div>
    @endif

    @if (! $this->exists)
        <div class="wy-page-note">
            <x-filament::icon icon="tabler-info-circle" class="h-5 w-5" />
            <p>{{ trans('wyvern.fivem.config.missing') }}</p>
        </div>
    @else
        @unless ($this->canEdit())
            <div class="wy-page-note">
                <x-filament::icon icon="tabler-lock" class="h-5 w-5" />
                <p>{{ trans('wyvern.properties.read_only') }}</p>
            </div>
        @endunless

        @include('wyvern.partials.unsaved-guard')

        {{ $this->form }}

        <p class="wy-page-footnote">{{ trans('wyvern.fivem.config.managed') }}</p>

        <div class="wy-page-actions">
            {{ $this->saveAction }}
        </div>
    @endif
</x-filament-panels::page>
