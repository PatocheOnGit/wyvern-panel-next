@php
    $installed = $this->installed();
    $channels = $this->channels();
    $builds = $this->builds();
    $selectedBuild = collect($channels)->get($this->choice)['build'] ?? strtok($this->choice, '-');
@endphp

<x-filament-panels::page>
    <div class="wy-version">
        <div class="wy-version-hero">
            <div class="wy-version-hero-mark">
                <x-filament::icon icon="tabler-server-bolt" class="h-8 w-8" />
            </div>

            <div class="wy-version-hero-text">
                <span class="wy-version-eyebrow">{{ trans('wyvern.version.installed') }}</span>
                <h2 class="wy-version-hero-name">{{ $this->fivem()->game() === 'redm' ? 'RedM' : 'FiveM' }}</h2>
                <p class="wy-version-hero-sub">
                    {{ $this->fivem()->usesTxAdmin() ? trans('wyvern.fivem.artifact.with_txadmin') : trans('wyvern.fivem.artifact.without_txadmin') }}
                </p>
            </div>

            <dl class="wy-version-facts">
                <div class="wy-version-fact">
                    <dt>{{ trans('wyvern.fivem.artifact.build') }}</dt>
                    <dd>{{ isset($installed['build']) ? strtok($installed['build'], '-') : '—' }}</dd>
                </div>
                <div class="wy-version-fact">
                    <dt>{{ trans('wyvern.fivem.artifact.channel') }}</dt>
                    <dd>{{ $installed['channel'] ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <section class="wy-version-step">
            <header class="wy-version-step-head">
                <span class="wy-version-eyebrow">{{ trans('wyvern.fivem.artifact.channels') }}</span>
                <p>{{ trans('wyvern.fivem.artifact.channels_help') }}</p>
            </header>

            <div class="wy-loader-grid">
                @foreach ($channels as $channel => $info)
                    <button type="button"
                            wire:click="select('{{ $channel }}')"
                            wire:key="channel-{{ $channel }}"
                            aria-pressed="{{ $this->choice === $channel ? 'true' : 'false' }}"
                            class="wy-loader-tile">
                        <span class="wy-loader-name">{{ trans("wyvern.fivem.artifact.channel_names.$channel") }}</span>
                        <span class="wy-loader-summary">
                            {{ trans('wyvern.fivem.artifact.build_line', ['build' => $info['build']]) }}
                            @if ($info['txadmin'])
                                · txAdmin {{ $info['txadmin'] }}
                            @endif
                        </span>
                        @if (($installed['channel'] ?? null) === $channel)
                            <span class="wy-loader-badge">{{ trans('wyvern.version.installed') }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </section>

        <section class="wy-version-step">
            <header class="wy-version-step-head">
                <span class="wy-version-eyebrow">{{ trans('wyvern.fivem.artifact.exact') }}</span>
                <p>{{ trans('wyvern.fivem.artifact.exact_help') }}</p>
            </header>

            <select class="wy-select" wire:model.live="choice">
                @foreach ($channels as $channel => $info)
                    <option value="{{ $channel }}">{{ trans("wyvern.fivem.artifact.channel_names.$channel") }} ({{ $info['build'] }})</option>
                @endforeach
                @foreach ($builds as $value => $number)
                    <option value="{{ $value }}">{{ trans('wyvern.fivem.artifact.build_line', ['build' => $number]) }}</option>
                @endforeach
            </select>
        </section>

        <div class="wy-version-foot">
            <p class="wy-version-plan">
                <x-filament::icon icon="tabler-arrow-right" class="h-4 w-4" />
                {{ trans('wyvern.fivem.artifact.plan', ['build' => $selectedBuild]) }}
            </p>

            {{ $this->installAction }}
        </div>
    </div>
</x-filament-panels::page>
