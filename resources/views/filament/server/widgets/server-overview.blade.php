<x-filament-widgets::widget>
    <div class="wy-overview" wire:poll.1s>
        <div class="wy-overview-bar">
            <span class="wy-overview-state fi-color fi-color-{{ $condition->getColor() }}">
                <i class="wy-server-card-dot"></i>
                {{ $condition->getLabel() }}
                @if ($uptime)
                    <span class="wy-overview-uptime">{{ $uptime }}</span>
                @endif
            </span>

            @if ($address)
                <button type="button" class="wy-overview-fact wy-overview-copy"
                        title="{{ trans('wyvern.cards.copy_address') }}"
                        x-on:click="
                            navigator.clipboard.writeText(@js($address));
                            $tooltip(@js(trans('wyvern.cards.copied')), { theme: $store.theme, timeout: 2000 })">
                    <x-filament::icon icon="tabler-network" class="h-4 w-4" />
                    <span class="wy-overview-mono">{{ $address }}</span>
                    <x-filament::icon icon="tabler-copy" class="wy-overview-copy-icon h-3.5 w-3.5" />
                </button>
            @endif

            @if ($software)
                <span class="wy-overview-fact">
                    <x-filament::icon icon="tabler-package" class="h-4 w-4" />
                    {{ $software }}
                </span>
            @endif

            @if ($software)
                <span class="wy-overview-fact wy-overview-players">
                    <x-filament::icon icon="tabler-users" class="h-4 w-4" />
                    <span class="wy-overview-mono">{{ $players ? $players['online'] . ' / ' . $players['max'] : '—' }}</span>
                </span>
            @endif
        </div>

        <div class="wy-overview-meters">
            @foreach ($meters as $label => $meter)
                @php
                    $ratio = $meter['ratio'] === null ? null : max(0, min(1, $meter['ratio']));
                    $colour = match (true) {
                        $ratio === null => 'var(--wy-line)',
                        $ratio >= 0.9 => 'var(--wy-offline)',
                        $ratio >= 0.7 => 'var(--wy-transition)',
                        default => 'var(--wy-accent)',
                    };
                @endphp
                <div class="wy-overview-meter">
                    <div class="wy-overview-meter-head">
                        <span class="wy-overview-meter-label">{{ $label }}</span>
                        <span class="wy-overview-meter-value">{{ $meter['value'] }}</span>
                    </div>
                    <span @class(['wy-server-card-meter', 'wy-server-card-meter-unmetered' => $ratio === null])>
                        @if ($ratio !== null)
                            <i style="width: {{ $ratio * 100 }}%; background: {{ $colour }};"></i>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
