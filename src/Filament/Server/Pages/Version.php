<?php

namespace Wyvern\Filament\Server\Pages;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Server\Pages\ServerFormPage;
use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Services\Servers\ReinstallServerService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\VersionCatalogue;

/**
 * Pick a server flavour and a version, and install it.
 *
 * The lists come from each flavour's own upstream API through VersionCatalogue, so they
 * are whatever is published today rather than whatever was true when the egg was
 * written. Choosing writes the egg's variables and asks the daemon to reinstall — the
 * install script then resolves the same thing on the node.
 *
 * The flavour is picked from a grid of marks rather than a dropdown, so the page shows
 * what it is offering; only the version and build lists, which are long, stay selects.
 *
 * Only servers whose egg exposes MC_LOADER can be driven from here; anything else is
 * told so plainly instead of being offered a picker that would do nothing.
 */
class Version extends ServerFormPage
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Package;

    protected static ?int $navigationSort = 1;

    protected string $view = 'wyvern.server.version';

    /**
     * What the server is running now, read once at mount so the page can show the
     * selection against it.
     *
     * @var array<string, string>
     */
    public array $installed = [];

    public function mount(): void
    {
        parent::mount();

        $this->installed = $this->currentValues();
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->columns(['default' => 1, 'sm' => 2])
            ->components([
                Hidden::make('MC_LOADER'),

                Select::make('MC_VERSION')
                    ->label(trans('wyvern.version.fields.version'))
                    ->options(fn (Get $get): array => $this->versionOptions($get('MC_LOADER')))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('MC_BUILD', 'latest')),

                Select::make('MC_BUILD')
                    ->label(trans('wyvern.version.fields.build'))
                    ->options(fn (Get $get): array => $this->buildOptions($get('MC_LOADER'), $get('MC_VERSION')))
                    ->searchable()
                    ->required()
                    ->helperText(trans('wyvern.version.fields.build_help')),
            ]);
    }

    protected function fillForm(): void
    {
        $this->form->fill($this->currentValues());
    }

    /** Choosing a flavour drops the version and build, which belong to it. */
    public function selectLoader(string $loader): void
    {
        if (!$this->isSupported() || !Loader::tryFrom($loader)) {
            return;
        }

        $this->form->fill([
            'MC_LOADER' => $loader,
            'MC_VERSION' => 'latest',
            'MC_BUILD' => 'latest',
        ]);
    }

    public function selectedLoader(): ?Loader
    {
        return Loader::tryFrom((string) ($this->data['MC_LOADER'] ?? ''));
    }

    public function installedLoader(): ?Loader
    {
        return Loader::tryFrom($this->installed['MC_LOADER'] ?? '');
    }

    /** True once the selection is something other than what is on disk. */
    public function hasChanges(): bool
    {
        foreach (['MC_LOADER', 'MC_VERSION', 'MC_BUILD'] as $name) {
            if (($this->data[$name] ?? null) !== ($this->installed[$name] ?? null)) {
                return true;
            }
        }

        return false;
    }

    public function isSupported(): bool
    {
        return $this->variable('MC_LOADER') !== null;
    }

    /** @return array<Action> */
    protected function getDefaultHeaderActions(): array
    {
        return [];
    }

    public function installAction(): Action
    {
        return Action::make('install')
            ->label(trans('wyvern.version.actions.install'))
            ->icon(TablerIcon::Download)
            ->color('primary')
            ->size(Size::Large)
            // The panel-wide preference turns actions into bare icons; the one
            // button this page exists for keeps its label.
            ->button()
            ->authorize(fn (): bool => $this->isSupported()
                && (user()?->can(SubuserPermission::SettingsReinstall, $this->getRecord()) ?? false))
            ->requiresConfirmation()
            ->modalHeading(trans('wyvern.version.actions.confirm_heading'))
            ->modalDescription(trans('wyvern.version.actions.confirm_body'))
            ->action(fn () => $this->install());
    }

    public function install(): void
    {
        $data = $this->form->getState();
        $server = $this->getRecord();

        foreach (['MC_LOADER', 'MC_VERSION', 'MC_BUILD'] as $name) {
            $variable = $this->variable($name);

            if (!$variable) {
                continue;
            }

            ServerVariable::query()->updateOrCreate(
                ['server_id' => $server->id, 'variable_id' => $variable->id],
                ['variable_value' => (string) ($data[$name] ?? '')],
            );
        }

        Activity::event('server:wyvern.version')
            ->property([
                'loader' => $data['MC_LOADER'] ?? null,
                'version' => $data['MC_VERSION'] ?? null,
                'build' => $data['MC_BUILD'] ?? null,
            ])
            ->log();

        try {
            app(ReinstallServerService::class)->handle($server);
        } catch (\Throwable $e) {
            Notification::make()
                ->title(trans('wyvern.version.notifications.failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->installed = $this->currentValues();

        Notification::make()
            ->title(trans('wyvern.version.notifications.started', [
                'loader' => Loader::tryFrom($data['MC_LOADER'] ?? '')?->label() ?? '?',
                'version' => $data['MC_VERSION'] ?? '?',
            ]))
            ->body(trans('wyvern.version.notifications.started_body'))
            ->success()
            ->send();
    }

    /** @return array<string, string> */
    private function versionOptions(?string $loader): array
    {
        $loader = Loader::tryFrom((string) $loader);

        if (!$loader) {
            return ['latest' => trans('wyvern.version.latest')];
        }

        $versions = app(VersionCatalogue::class)->gameVersions($loader);

        return ['latest' => trans('wyvern.version.latest')]
            + array_combine($versions, $versions);
    }

    /** @return array<string, string> */
    private function buildOptions(?string $loader, ?string $version): array
    {
        $loader = Loader::tryFrom((string) $loader);

        if (!$loader || !filled($version) || $version === 'latest') {
            return ['latest' => trans('wyvern.version.latest')];
        }

        $builds = app(VersionCatalogue::class)->builds($loader, $version);

        return ['latest' => trans('wyvern.version.latest')]
            + array_combine($builds, $builds);
    }

    /** @return array<string, string> */
    private function currentValues(): array
    {
        $values = [];

        foreach (['MC_LOADER', 'MC_VERSION', 'MC_BUILD'] as $name) {
            $variable = $this->variable($name);

            $values[$name] = $variable
                ? (ServerVariable::query()
                    ->where('server_id', $this->getRecord()->id)
                    ->where('variable_id', $variable->id)
                    ->value('variable_value') ?: $variable->default_value)
                : 'latest';
        }

        return $values;
    }

    private function variable(string $name): ?EggVariable
    {
        return EggVariable::query()
            ->where('egg_id', $this->getRecord()->egg_id)
            ->where('env_variable', $name)
            ->first();
    }

    public static function canAccess(): bool
    {
        /** @var Server|null $server */
        $server = Filament::getTenant();

        return $server !== null
            && EggVariable::query()
                ->where('egg_id', $server->egg_id)
                ->where('env_variable', 'MC_LOADER')
                ->exists();
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('wyvern.navigation.software');
    }

    public static function getNavigationLabel(): string
    {
        return trans('wyvern.version.title');
    }

    public function getTitle(): string
    {
        return trans('wyvern.version.title');
    }
}
