<?php

namespace App\Enums;

enum CustomizationKey: string
{
    case ConsoleRows = 'console_rows';
    case ConsoleFont = 'console_font';
    case ConsoleFontSize = 'console_font_size';
    case ConsoleGraphPeriod = 'console_graph_period';
    case TopNavigation = 'top_navigation';
    case DashboardLayout = 'dashboard_layout';

    case ButtonStyle = 'button_style';
    case Density = 'density';
    case PinnedServers = 'pinned_servers';
    case RedirectToAdmin = 'redirect_to_admin';

    /** @return string|int|bool|array<int, string> */
    public function getDefaultValue(): string|int|bool|array
    {
        return match ($this) {
            self::ConsoleRows => 30,
            self::ConsoleFont => 'monospace',
            self::ConsoleFontSize => 14,
            self::ConsoleGraphPeriod => 30,
            self::TopNavigation => config('panel.filament.default-navigation', 'sidebar'),
            self::DashboardLayout => 'grid',
            self::ButtonStyle => true,
            self::Density => 'compact',
            self::PinnedServers => [],
            self::RedirectToAdmin => false,
        };
    }

    /** @return array<string, string|int|bool|array<int, string>> */
    public static function getDefaultCustomization(): array
    {
        $default = [];

        foreach (self::cases() as $key) {
            $default[$key->value] = $key->getDefaultValue();
        }

        return $default;
    }
}
