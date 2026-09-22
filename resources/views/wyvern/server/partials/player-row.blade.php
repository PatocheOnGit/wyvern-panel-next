@php
    $lower = strtolower($name);
    $isOp = isset($ops[$lower]);
    $isWhitelisted = isset($whitelisted[$lower]);
    $isBanned = isset($banned[$lower]);
@endphp

<li class="wy-player-row" wire:key="{{ $online ? 'on' : 'seen' }}-{{ $uuid }}">
    <img class="wy-player-head" src="{{ route('wyvern.heads', ['name' => $name]) }}" alt="" loading="lazy"
         onerror="this.replaceWith(Object.assign(document.createElement('span'), {className: 'wy-player-head'}))">

    <div class="wy-player-text">
        <span class="wy-player-name">
            {{ $name }}
            @if ($isOp)
                <span class="wy-player-badge">{{ trans('wyvern.players.badges.op') }}</span>
            @endif
            @if ($isBanned)
                <span class="wy-player-badge wy-player-badge-danger">{{ trans('wyvern.players.badges.banned') }}</span>
            @endif
        </span>
        <span class="wy-player-meta">{{ $uuid }}</span>
    </div>

    <div class="wy-player-actions">
        <button type="button" class="wy-row-button" wire:click="toggle('whitelist', @js($name))" wire:loading.attr="disabled">
            {{ trans($isWhitelisted ? 'wyvern.players.actions.unwhitelist' : 'wyvern.players.actions.whitelist') }}
        </button>
        <button type="button" class="wy-row-button" wire:click="toggle('ops', @js($name))" wire:loading.attr="disabled">
            {{ trans($isOp ? 'wyvern.players.actions.deop' : 'wyvern.players.actions.op') }}
        </button>
        @if ($online)
            <button type="button" class="wy-row-button" wire:click="mountAction('kick', { name: @js($name) })">
                {{ trans('wyvern.players.actions.kick') }}
            </button>
        @endif
        @if ($isBanned)
            <button type="button" class="wy-row-button" wire:click="remove('bans', @js($name))" wire:loading.attr="disabled">
                {{ trans('wyvern.players.actions.pardon') }}
            </button>
        @else
            <button type="button" class="wy-row-button wy-row-button-danger" wire:click="mountAction('ban', { name: @js($name) })">
                {{ trans('wyvern.players.actions.ban') }}
            </button>
        @endif
    </div>
</li>
