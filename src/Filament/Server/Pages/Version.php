<?php

namespace Wyvern\Filament\Server\Pages;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Server\Pages\ServerFormPage;
use App\Models\EggVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Illuminate\Validation\ValidationException;
use Wyvern\Filament\Forms\BackupToggle;
use Wyvern\Jobs\ChangeServerJob;
use Wyvern\Minecraft\InstallRecords;
use Wyvern\Minecraft\JavaImage;
use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\Reinstaller;
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
    private const VARIABLES = Reinstaller::VARIABLES;

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

    /** @var array<string, array{version: string, required: int, image: string, target: ?int, current: ?int}|null> */
    private array $javaPlans = [];

    protected VersionCatalogue $catalogue;

    protected InstallRecords $records;

    protected Reinstaller $reinstaller;

    public function boot(VersionCatalogue $catalogue, InstallRecords $records, Reinstaller $reinstaller): void
    {
        $this->catalogue = $catalogue;
        $this->records = $records;
        $this->reinstaller = $reinstaller;
    }

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
                    ->afterStateUpdated(function (Set $set) {
                        $set('MC_BUILD', 'latest');
                        $set('switch_java', $this->canChangeImage());
                    }),

                Select::make('MC_BUILD')
                    ->label(trans('wyvern.version.fields.build'))
                    ->options(fn (Get $get): array => $this->buildOptions($get('MC_LOADER'), $get('MC_VERSION')))
                    ->searchable()
                    ->required()
                    ->helperText(trans('wyvern.version.fields.build_help')),

                Toggle::make('switch_java')
                    ->label(fn (Get $get): string => trans('wyvern.version.java.switch', [
                        'java' => $this->javaPlan($get('MC_LOADER'), $get('MC_VERSION'))['target'] ?? '?',
                    ]))
                    // helperText() and belowContent() share one slot, so both go here.
                    ->belowContent(fn (Get $get): array => array_values(array_filter([
                        $this->javaNeeds($get('MC_LOADER'), $get('MC_VERSION')),
                        $get('switch_java') ? null : Text::make($this->javaWarning($get('MC_LOADER'), $get('MC_VERSION')))
                            ->color('danger')
                            ->icon(TablerIcon::AlertTriangle),
                    ])))
                    ->visible(fn (Get $get): bool => $this->javaPlan($get('MC_LOADER'), $get('MC_VERSION')) !== null)
                    ->disabled(fn (): bool => !$this->canChangeImage())
                    ->live()
                    ->columnSpanFull(),

                BackupToggle::make($this->getRecord()),
            ]);
    }

    protected function fillForm(): void
    {
        $this->form->fill($this->currentValues() + ['switch_java' => $this->canChangeImage(), 'backup' => false]);
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
            'switch_java' => $this->canChangeImage(),
            'backup' => (bool) ($this->data['backup'] ?? false),
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

    /** An installed "latest" shown with what it resolved to on disk. */
    public function installedValue(string $name): string
    {
        $value = $this->installed[$name] ?? null;

        if ($value === 'latest') {
            $record = $this->records->of($this->getRecord());
            $resolved = $record?->loader === $this->installedLoader()
                ? ($name === 'MC_VERSION' ? $record?->minecraft : $record?->build)
                : null;

            if ($resolved !== null) {
                return trans('wyvern.version.resolved', ['value' => $resolved]);
            }
        }

        return $value ?? '—';
    }

    /** True once the selection is something other than what is on disk. */
    public function hasChanges(): bool
    {
        foreach (self::VARIABLES as $name) {
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
            ->modalDescription(fn (): string => trim(trans('wyvern.version.actions.confirm_body') . ' ' . ($this->data['switch_java'] ?? false
                ? ''
                : $this->javaWarning($this->data['MC_LOADER'] ?? null, $this->data['MC_VERSION'] ?? null))))
            ->action(fn () => $this->install());
    }

    public function install(): void
    {
        $data = $this->form->getState();
        $server = $this->getRecord();
        $loader = Loader::tryFrom((string) ($data['MC_LOADER'] ?? ''));

        if (!$loader) {
            $this->notifyFailed(trans('wyvern.version.errors.loader'));

            return;
        }

        // Same rules and user_editable checks as the Startup page.
        try {
            $values = $this->reinstaller->validate($server, $data);
        } catch (ValidationException $e) {
            $this->notifyFailed($e->validator->errors()->first());

            return;
        }

        if (!isset($values['MC_LOADER'])) {
            $this->notifyFailed(trans('wyvern.version.errors.locked'));

            return;
        }

        $plan = ($data['switch_java'] ?? false) && $this->canChangeImage()
            ? $this->javaPlan($loader->value, $data['MC_VERSION'] ?? null)
            : null;
        $previousImage = $server->image;

        // A backup has to finish before the reinstall starts, so both go to the queue.
        if (($data['backup'] ?? false) && BackupToggle::available($server)) {
            ChangeServerJob::dispatch($server, user(), $values, $plan['image'] ?? null, true);

            Notification::make()
                ->title(trans('wyvern.version.notifications.queued'))
                ->body(trans('wyvern.version.notifications.queued_body'))
                ->success()
                ->send();

            return;
        }

        try {
            $this->reinstaller->apply($server, $values, $plan['image'] ?? null);
        } catch (\Throwable $e) {
            $this->notifyFailed($e->getMessage());

            return;
        }

        Activity::event('server:wyvern.version')
            ->property([
                'loader' => $data['MC_LOADER'] ?? null,
                'version' => $data['MC_VERSION'] ?? null,
                'build' => $data['MC_BUILD'] ?? null,
            ])
            ->log();

        if ($plan) {
            Activity::event('server:startup.image')
                ->property(['old' => $previousImage, 'new' => $plan['image']])
                ->log();
        }

        $this->installed = $this->currentValues();

        Notification::make()
            ->title(trans('wyvern.version.notifications.started', [
                'loader' => $loader->label(),
                'version' => $data['MC_VERSION'] ?? '?',
            ]))
            ->body(trans('wyvern.version.notifications.started_body'))
            ->success()
            ->send();
    }

    private function notifyFailed(string $message): void
    {
        Notification::make()
            ->title(trans('wyvern.version.notifications.failed'))
            ->body($message)
            ->danger()
            ->send();
    }

    private function canChangeImage(): bool
    {
        return user()?->can(SubuserPermission::StartupDockerImage, $this->getRecord()) ?? false;
    }

    /**
     * The image this selection needs, when it is not the one the server has.
     *
     * @return array{version: string, required: int, image: string, target: ?int, current: ?int}|null
     */
    private function javaPlan(?string $loader, ?string $version): ?array
    {
        $loader = Loader::tryFrom((string) $loader);

        if (!$loader || !filled($version)) {
            return null;
        }

        $key = $loader->value . '@' . $version;

        if (array_key_exists($key, $this->javaPlans)) {
            return $this->javaPlans[$key];
        }

        $server = $this->getRecord();
        $resolved = $this->catalogue->resolve($loader, $version);
        $required = $resolved ? $this->catalogue->javaVersion($resolved) : null;
        $image = $required ? JavaImage::for($server->egg, $required) : null;

        return $this->javaPlans[$key] = $image && $image !== $server->image
            ? [
                'version' => $resolved,
                'required' => $required,
                'image' => $image,
                'target' => JavaImage::major($image),
                'current' => JavaImage::major($server->image),
            ]
            : null;
    }

    private function javaNeeds(?string $loader, ?string $version): ?string
    {
        $plan = $this->javaPlan($loader, $version);

        if (!$plan) {
            return null;
        }

        if (!$this->canChangeImage()) {
            return trans('wyvern.version.java.no_permission');
        }

        return trans('wyvern.version.java.needs', [
            'version' => $plan['version'],
            'required' => $plan['required'],
            'current' => $plan['current'] !== null
                ? trans('wyvern.version.java.java', ['java' => $plan['current']])
                : trans('wyvern.version.java.custom_image'),
        ]);
    }

    private function javaWarning(?string $loader, ?string $version): ?string
    {
        $plan = $this->javaPlan($loader, $version);

        if (!$plan) {
            return null;
        }

        $older = $plan['current'] === null || $plan['current'] < $plan['required'];

        return trans($older ? 'wyvern.version.java.declined_older' : 'wyvern.version.java.declined_newer', [
            'required' => $plan['required'],
        ]);
    }

    /** @return array<string, string> */
    private function versionOptions(?string $loader): array
    {
        $loader = Loader::tryFrom((string) $loader);

        if (!$loader) {
            return ['latest' => trans('wyvern.version.latest')];
        }

        $versions = $this->catalogue->gameVersions($loader);

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

        $builds = $this->catalogue->builds($loader, $version);

        return ['latest' => trans('wyvern.version.latest')]
            + array_combine($builds, $builds);
    }

    /** @return array<string, string> */
    private function currentValues(): array
    {
        $values = [];

        foreach (self::VARIABLES as $name) {
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
