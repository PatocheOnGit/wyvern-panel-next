<?php

namespace App\Filament\Server\Components;

use Filament\Support\Concerns\CanBeCopied;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SmallStatBlock extends Stat
{
    use CanBeCopied;

    protected string $view = 'filament.components.server-small-data-block';

    /**
     * How full the thing being measured is, 0 to 1, or null when there is nothing to
     * measure against.
     *
     * The block used to carry only a pre-formatted string — "1.16 GiB / 4 GiB" — which
     * is a sentence, not a quantity, so nothing downstream could draw a bar from it. A
     * null ratio is a real state and not an absence: a stopped server, or a limit set to
     * unlimited, has no fullness, and a bar at zero would claim it was idle instead.
     */
    protected ?float $ratio = null;

    public function ratio(?float $ratio): static
    {
        $this->ratio = $ratio === null ? null : max(0.0, min(1.0, $ratio));

        return $this;
    }

    public function getRatio(): ?float
    {
        return $this->ratio;
    }

    /**
     * Status, at the same thresholds the server cards use, so a server that looks tight
     * on the home page looks tight on its own console too.
     */
    public function getRatioColor(): string
    {
        return match (true) {
            $this->ratio === null => 'var(--wy-line)',
            $this->ratio >= 0.9 => 'var(--wy-offline)',
            $this->ratio >= 0.7 => 'var(--wy-transition)',
            default => 'var(--wy-accent)',
        };
    }
}
