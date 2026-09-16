@php
    use Wyvern\Minecraft\Loader;

    $selected = $this->selectedLoader();
    $installed = $this->installedLoader();
    $changed = $this->hasChanges();
@endphp

<x-filament-panels::page>
    @if (! $this->isSupported())
        <div class="wy-version-notice">
            <x-filament::icon icon="tabler-alert-triangle" class="h-5 w-5" />
            <div>
                <p class="wy-version-notice-title">{{ trans('wyvern.version.unsupported.heading') }}</p>
                <p>{{ trans('wyvern.version.unsupported.body', ['egg' => $this->getRecord()->egg->name]) }}</p>
            </div>
        </div>
    @else
        <div class="wy-version">

            <div class="wy-version-hero">
                <div class="wy-version-hero-mark" @if ($installed) data-loader="{{ $installed->value }}" @endif>
                    @if ($installed)
                        <img src="{{ $installed->logo() }}" alt="">
                    @else
                        <x-filament::icon icon="tabler-package" class="h-8 w-8" />
                    @endif
                </div>

                <div class="wy-version-hero-text">
                    <span class="wy-version-eyebrow">{{ trans('wyvern.version.installed') }}</span>
                    <h2 class="wy-version-hero-name">{{ $installed?->label() ?? trans('wyvern.version.unknown') }}</h2>
                    <p class="wy-version-hero-sub">{{ $installed?->summary() }}</p>
                </div>

                <dl class="wy-version-facts">
                    <div class="wy-version-fact">
                        <dt>{{ trans('wyvern.version.fields.version') }}</dt>
                        <dd>{{ $this->installed['MC_VERSION'] ?? '—' }}</dd>
                    </div>
                    <div class="wy-version-fact">
                        <dt>{{ trans('wyvern.version.fields.build') }}</dt>
                        <dd>{{ $this->installed['MC_BUILD'] ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <section class="wy-version-step">
                <header class="wy-version-step-head">
                    <span class="wy-version-eyebrow">{{ trans('wyvern.version.steps.flavour') }}</span>
                    <p>{{ trans('wyvern.version.body') }}</p>
                </header>

                <div class="wy-loader-grid">
                    @foreach (Loader::cases() as $loader)
                        <button type="button"
                                wire:click="selectLoader('{{ $loader->value }}')"
                                wire:key="loader-{{ $loader->value }}"
                                data-loader="{{ $loader->value }}"
                                aria-pressed="{{ $loader === $selected ? 'true' : 'false' }}"
                                class="wy-loader-tile">
                            <span class="wy-loader-mark" data-loader="{{ $loader->value }}">
                                <img src="{{ $loader->logo() }}" alt="" loading="lazy">
                            </span>
                            <span class="wy-loader-name">{{ $loader->label() }}</span>
                            <span class="wy-loader-summary">{{ $loader->summary() }}</span>
                            @if ($loader === $installed)
                                <span class="wy-loader-badge">{{ trans('wyvern.version.installed') }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="wy-version-step">
                <header class="wy-version-step-head">
                    <span class="wy-version-eyebrow">{{ trans('wyvern.version.steps.version') }}</span>
                    <p>{{ trans('wyvern.version.steps.version_help') }}</p>
                </header>

                {{ $this->form }}
            </section>

            <div class="wy-version-foot">
                <p class="wy-version-plan">
                    @if ($changed)
                        <x-filament::icon icon="tabler-arrow-right" class="h-4 w-4" />
                        {{ trans('wyvern.version.plan', [
                            'loader' => $selected?->label() ?? '—',
                            'version' => $this->data['MC_VERSION'] ?? '—',
                            'build' => $this->data['MC_BUILD'] ?? '—',
                        ]) }}
                    @else
                        <x-filament::icon icon="tabler-check" class="h-4 w-4" />
                        {{ trans('wyvern.version.up_to_date') }}
                    @endif
                </p>

                {{ $this->installAction }}
            </div>
        </div>
    @endif
</x-filament-panels::page>
