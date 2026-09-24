@php
    $rows = $this->rows();
    $layout = $this->layout();
@endphp

<x-filament-panels::page>
    <div class="wy-content">
        @if ($layout->pending)
            <div class="wy-page-note">
                <x-filament::icon icon="tabler-info-circle" class="h-5 w-5" />
                <p>{{ trans('wyvern.fivem.txadmin.pending') }}</p>
            </div>
        @elseif ($this->fivem()->usesTxAdmin())
            <div class="wy-page-note">
                <x-filament::icon icon="tabler-info-circle" class="h-5 w-5" />
                <p>{{ trans('wyvern.fivem.txadmin.deployment', ['path' => $layout->root]) }}</p>
            </div>
        @endif

        <div class="wy-content-bar">
            <div class="wy-content-search">
                <x-filament::icon icon="tabler-search" class="h-4 w-4" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ trans('wyvern.fivem.resources.search') }}">
            </div>
            <p class="wy-content-summary-line">
                {{ trans_choice('wyvern.fivem.resources.count', count($rows), ['count' => count($rows), 'on' => collect($rows)->where('ensured', true)->count()]) }}
            </p>
        </div>

        @if ($rows === [])
            <div class="wy-content-empty">
                <x-filament::icon icon="tabler-mood-empty" class="h-8 w-8" />
                <p>{{ trans('wyvern.fivem.resources.empty') }}</p>
            </div>
        @else
            <ul class="wy-player-list">
                @foreach ($rows as $row)
                    <li class="wy-player-row" wire:key="res-{{ $row['path'] }}">
                        <span @class(['wy-dot', 'wy-dot-on' => $row['ensured']])></span>
                        <div class="wy-player-text">
                            <span class="wy-player-name">
                                {{ $row['name'] }}
                                @if ($row['via'])
                                    <span class="wy-player-badge wy-player-badge-muted">{{ trans('wyvern.fivem.resources.via', ['category' => $row['via']]) }}</span>
                                @endif
                                @if ($row['origin'] === 'builtin')
                                    <span class="wy-player-badge wy-player-badge-muted">{{ trans('wyvern.fivem.resources.builtin') }}</span>
                                @elseif ($row['origin'] === 'missing')
                                    <span class="wy-player-badge wy-player-badge-danger">{{ trans('wyvern.fivem.resources.missing') }}</span>
                                @endif
                            </span>
                            <span class="wy-player-meta">
                                {{ match ($row['origin']) {
                                    'builtin' => trans('wyvern.fivem.resources.builtin_help'),
                                    'missing' => trans('wyvern.fivem.resources.missing_help'),
                                    default => $row['category'] ?? trans('wyvern.fivem.resources.uncategorised'),
                                } }}
                            </span>
                        </div>
                        <div class="wy-player-actions">
                            @if (! $row['via'])
                                <button type="button" class="wy-row-button" wire:click="toggle(@js($row['name']))" wire:loading.attr="disabled">
                                    {{ trans($row['ensured'] ? 'wyvern.fivem.resources.stop' : 'wyvern.fivem.resources.ensure') }}
                                </button>
                            @endif
                            @if ($row['origin'] === 'folder')
                                <button type="button" class="wy-row-button wy-row-button-danger"
                                        wire:click="delete(@js($row['name']))"
                                        wire:confirm="{{ trans('wyvern.fivem.resources.delete_confirm', ['name' => $row['name']]) }}"
                                        wire:loading.attr="disabled">
                                    {{ trans('wyvern.content.installed.delete') }}
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament-panels::page>
