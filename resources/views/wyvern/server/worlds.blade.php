@php
    $worlds = $this->worlds();
    $running = $this->isRunning();
    $datapacks = $this->datapacks();
@endphp

<x-filament-panels::page>
    <div class="wy-players">
        @if ($running)
            <div class="wy-page-note">
                <x-filament::icon icon="tabler-info-circle" class="h-5 w-5" />
                <p>{{ trans('wyvern.worlds.running_note') }}</p>
            </div>
        @endif

        <section class="wy-players-section">
            <h3 class="wy-players-heading">{{ trans('wyvern.worlds.title') }}</h3>

            @if ($worlds === [])
                <p class="wy-players-empty">{{ trans('wyvern.worlds.empty') }}</p>
            @else
                <ul class="wy-player-list">
                    @foreach ($worlds as $world)
                        <li class="wy-player-row" wire:key="world-{{ $world['name'] }}">
                            <span class="wy-player-head wy-player-head-ip">
                                <x-filament::icon icon="tabler-world" class="h-4 w-4" />
                            </span>
                            <div class="wy-player-text">
                                <span class="wy-player-name">
                                    {{ $world['name'] }}
                                    @if ($world['active'])
                                        <span class="wy-player-badge">{{ trans('wyvern.worlds.active') }}</span>
                                    @endif
                                </span>
                                <span class="wy-player-meta">{{ implode(' · ', $world['folders']) }}</span>
                            </div>
                            <div class="wy-player-actions">
                                <button type="button" class="wy-row-button" wire:click="download(@js($world['name']))" wire:loading.attr="disabled">
                                    {{ trans('wyvern.worlds.download') }}
                                </button>
                                @unless ($world['active'])
                                    <button type="button" class="wy-row-button" wire:click="useWorld(@js($world['name']))" wire:loading.attr="disabled">
                                        {{ trans('wyvern.worlds.use') }}
                                    </button>
                                    <button type="button" class="wy-row-button wy-row-button-danger"
                                            wire:click="delete(@js($world['name']))"
                                            wire:confirm="{{ trans('wyvern.worlds.delete_confirm', ['name' => $world['name']]) }}"
                                            wire:loading.attr="disabled">
                                        {{ trans('wyvern.content.installed.delete') }}
                                    </button>
                                @else
                                    <button type="button" class="wy-row-button wy-row-button-danger"
                                            wire:click="mountAction('reset', { world: @js($world['name']) })">
                                        {{ trans('wyvern.worlds.reset') }}
                                    </button>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="wy-players-section">
            <div class="wy-content-bar">
                <h3 class="wy-players-heading">{{ trans('wyvern.worlds.datapacks', ['world' => $this->levelName()]) }}</h3>
                <div class="wy-page-actions wy-push">{{ $this->addDatapackAction }}</div>
            </div>

            @if ($datapacks === [])
                <p class="wy-players-empty">{{ trans('wyvern.worlds.no_datapacks') }}</p>
            @else
                <ul class="wy-player-list">
                    @foreach ($datapacks as $pack)
                        <li class="wy-player-row" wire:key="dp-{{ $pack['name'] }}">
                            <span class="wy-player-head wy-player-head-ip">
                                <x-filament::icon icon="tabler-box" class="h-4 w-4" />
                            </span>
                            <div class="wy-player-text">
                                <span class="wy-player-name">{{ $pack['name'] }}</span>
                            </div>
                            <div class="wy-player-actions">
                                <button type="button" class="wy-row-button wy-row-button-danger"
                                        wire:click="deleteDatapack(@js($pack['name']))"
                                        wire:confirm="{{ trans('wyvern.worlds.delete_confirm', ['name' => $pack['name']]) }}">
                                    {{ trans('wyvern.content.installed.delete') }}
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-filament-panels::page>
