@php
    $running = $this->isRunning();
    $status = $this->status();
    $tab = $this->tab;
    $counts = [
        'whitelist' => count($this->entries('whitelist')),
        'ops' => count($this->entries('ops')),
        'bans' => count($this->entries('bans')),
        'ip_bans' => count($this->entries('ip_bans')),
    ];
    $ops = $this->membership('ops');
    $whitelisted = $this->membership('whitelist');
    $banned = $this->membership('bans');
@endphp

<x-filament-panels::page>
    <div class="wy-players" @if ($running) wire:poll.10s @endif>

        <div class="wy-players-strip">
            <div class="wy-players-stat">
                <span @class(['wy-dot', 'wy-dot-on' => $status !== null])></span>
                <span class="wy-players-stat-value">{{ $status ? $status['online'] . ' / ' . $status['max'] : '—' }}</span>
                <span class="wy-players-stat-label">
                    {{ $status ? trans('wyvern.players.online') : ($running ? trans('wyvern.players.starting') : trans('wyvern.players.offline')) }}
                </span>
            </div>

            <label class="wy-players-switch">
                <input type="checkbox"
                       @checked($this->whitelistEnabled())
                       wire:change="setWhitelist($event.target.checked)"
                       wire:loading.attr="disabled">
                <span>{{ trans('wyvern.players.whitelist_enabled') }}</span>
            </label>

            @unless ($running)
                <p class="wy-players-hint">{{ trans('wyvern.players.offline_hint') }}</p>
            @endunless
        </div>

        <div class="wy-content-tabs wy-players-tabs">
            @foreach (\Wyvern\Filament\Server\Pages\Players::TABS as $key)
                <button type="button"
                        wire:click="selectTab('{{ $key }}')"
                        @class(['wy-content-tab', 'wy-content-tab-on' => $key === $tab])>
                    {{ trans("wyvern.players.tabs.$key") }}
                    @if (isset($counts[$key]))
                        <span class="wy-players-count">{{ $counts[$key] }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        @if ($tab === 'online')
            <section class="wy-players-section">
                <h3 class="wy-players-heading">{{ trans('wyvern.players.online_now') }}</h3>

                @if ($status === null)
                    <p class="wy-players-empty">{{ $running ? trans('wyvern.players.no_status') : trans('wyvern.players.offline_list') }}</p>
                @elseif ($status['players'] === [])
                    <p class="wy-players-empty">
                        {{ $status['online'] > 0 ? trans('wyvern.players.hidden', ['count' => $status['online']]) : trans('wyvern.players.nobody') }}
                    </p>
                @else
                    <ul class="wy-player-list">
                        @foreach ($status['players'] as $player)
                            @include('wyvern.server.partials.player-row', ['name' => $player['name'], 'uuid' => $player['id'], 'online' => true])
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="wy-players-section">
                <h3 class="wy-players-heading">{{ trans('wyvern.players.recent') }}</h3>

                @if ($this->recent() === [])
                    <p class="wy-players-empty">{{ trans('wyvern.players.no_recent') }}</p>
                @else
                    <ul class="wy-player-list">
                        @foreach ($this->recent() as $player)
                            @include('wyvern.server.partials.player-row', ['name' => $player['name'], 'uuid' => $player['uuid'], 'online' => false])
                        @endforeach
                    </ul>
                @endif
            </section>
        @else
            <form class="wy-players-add" wire:submit="add">
                <input type="text"
                       wire:model="value"
                       required
                       maxlength="45"
                       placeholder="{{ trans($tab === 'ip_bans' ? 'wyvern.players.fields.ip' : 'wyvern.players.fields.name') }}">
                @if (in_array($tab, ['bans', 'ip_bans'], true))
                    <input type="text"
                           wire:model="reason"
                           maxlength="200"
                           placeholder="{{ trans('wyvern.players.fields.reason_optional') }}">
                @endif
                <button type="submit" class="wy-content-install" wire:loading.attr="disabled" wire:target="add">
                    <x-filament::icon icon="tabler-plus" class="h-3.5 w-3.5" />
                    {{ trans("wyvern.players.add.$tab") }}
                </button>
            </form>

            @php $entries = $this->entries($tab); @endphp

            @if ($entries === [])
                <p class="wy-players-empty">{{ trans("wyvern.players.empty.$tab") }}</p>
            @else
                <ul class="wy-player-list">
                    @foreach ($entries as $entry)
                        @php $key = $tab === 'ip_bans' ? ($entry['ip'] ?? '') : ($entry['name'] ?? ''); @endphp
                        <li class="wy-player-row" wire:key="{{ $tab }}-{{ $key }}">
                            @if ($tab === 'ip_bans')
                                <span class="wy-player-head wy-player-head-ip">
                                    <x-filament::icon icon="tabler-network" class="h-4 w-4" />
                                </span>
                            @else
                                <img class="wy-player-head" src="{{ route('wyvern.heads', ['name' => $key]) }}" alt="" loading="lazy"
                                     onerror="this.replaceWith(Object.assign(document.createElement('span'), {className: 'wy-player-head'}))">
                            @endif

                            <div class="wy-player-text">
                                <span class="wy-player-name">{{ $key }}</span>
                                <span class="wy-player-meta">
                                    @if ($tab === 'ops')
                                        {{ trans('wyvern.players.level', ['level' => $entry['level'] ?? 4]) }}
                                    @elseif (in_array($tab, ['bans', 'ip_bans'], true))
                                        {{ $entry['reason'] ?? '' }}
                                        @if (($entry['expires'] ?? 'forever') !== 'forever')
                                            · {{ trans('wyvern.players.until', ['date' => $entry['expires']]) }}
                                        @endif
                                    @else
                                        {{ $entry['uuid'] ?? '' }}
                                    @endif
                                </span>
                            </div>

                            <div class="wy-player-actions">
                                <button type="button"
                                        class="wy-row-button wy-row-button-danger"
                                        wire:click="remove('{{ $tab }}', @js($key))"
                                        wire:loading.attr="disabled">
                                    {{ trans("wyvern.players.remove.$tab") }}
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>
</x-filament-panels::page>
