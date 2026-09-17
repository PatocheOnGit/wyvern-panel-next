<?php

namespace App\Livewire;

use App\Filament\Server\Pages\Console;
use App\Models\Server;
use Filament\Support\Facades\FilamentView;
use Illuminate\View\View;
use Livewire\Component;
use Wyvern\Servers\Pins;

class ServerEntry extends Component
{
    public Server $server;

    public function render(): View
    {
        return view('livewire.server-entry', ['component' => $this]);
    }

    public function placeholder(): View
    {
        return view('livewire.server-entry-placeholder', ['server' => $this->server, 'component' => $this]);
    }

    public function isPinned(): bool
    {
        return Pins::has(user(), $this->server);
    }

    /**
     * The card owns the pin because the card is where you are when you decide a server
     * matters. It dispatches so the list can reorder: the component re-renders itself
     * happily, but its position among its siblings belongs to the page above it.
     */
    public function togglePin(): void
    {
        $user = user();

        if ($user === null) {
            return;
        }

        Pins::toggle($user, $this->server);

        $this->dispatch('wyvern-pins-changed');
    }

    public function redirectUrl(?bool $shouldOpenUrlInNewTab = false): string
    {
        $url = Console::getUrl(panel: 'server', tenant: $this->server);
        $target = $shouldOpenUrlInNewTab ? '_blank' : '_self';

        if (!$shouldOpenUrlInNewTab && FilamentView::hasSpaMode($url)) {
            return sprintf("Livewire.navigate('%s')", $url);
        }

        return sprintf("window.open('%s', '%s')", $url, $target);
    }
}
