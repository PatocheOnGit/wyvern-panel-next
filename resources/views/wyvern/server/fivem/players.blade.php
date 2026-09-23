@php
    $running = $this->isRunning();
    $players = $this->players();
    $dynamic = $this->dynamic();
@endphp

<x-filament-panels::page>
    <div class="wy-players" @if ($running) wire:poll.10s @endif>
        <div class="wy-players-strip">
            <div class="wy-players-stat">
                <span @class(['wy-dot', 'wy-dot-on' => $players !== null])></span>
                <span class="wy-players-stat-value">
                    {{ $players !== null ? count($players) . ' / ' . ($dynamic['sv_maxclients'] ?? '?') : '—' }}
                </span>
                <span class="wy-players-stat-label">
                    {{ $players !== null ? trans('wyvern.players.online') : ($running ? trans('wyvern.players.starting') : trans('wyvern.players.offline')) }}
                </span>
            </div>
        </div>

        @if ($players === null)
            <p class="wy-players-empty">
                {{ match (true) {
                    ! $running => trans('wyvern.players.offline_list'),
                    $this->fivem()->usesTxAdmin() => trans('wyvern.fivem.players.txadmin_idle'),
                    default => trans('wyvern.fivem.players.no_answer'),
                } }}
            </p>
        @elseif ($players === [])
            <p class="wy-players-empty">{{ trans('wyvern.players.nobody') }}</p>
        @else
            <ul class="wy-player-list">
                @foreach ($players as $player)
                    <li class="wy-player-row" wire:key="fx-{{ $player['id'] ?? $loop->index }}">
                        <span class="wy-player-head wy-player-head-ip">
                            <span class="wy-player-id">{{ $player['id'] ?? '?' }}</span>
                        </span>
                        <div class="wy-player-text">
                            <span class="wy-player-name">
                                {{ $player['name'] ?? '?' }}
                                <span class="wy-player-badge wy-player-badge-muted">{{ trans('wyvern.fivem.players.ping', ['ping' => $player['ping'] ?? '?']) }}</span>
                            </span>
                            <span class="wy-player-meta">{{ implode(' · ', \Wyvern\Filament\Server\Pages\FiveM\PlayerList::identifiers($player)) }}</span>
                        </div>
                        <div class="wy-player-actions">
                            <button type="button" class="wy-row-button wy-row-button-danger"
                                    wire:click="mountAction('kick', { id: {{ (int) ($player['id'] ?? 0) }}, name: @js($player['name'] ?? '') })">
                                {{ trans('wyvern.players.actions.kick') }}
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="wy-page-footnote">{{ trans('wyvern.fivem.players.bans_note') }}</p>
    </div>
</x-filament-panels::page>
