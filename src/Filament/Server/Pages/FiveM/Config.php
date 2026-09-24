<?php

namespace Wyvern\Filament\Server\Pages\FiveM;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Services\Databases\DeployServerDatabaseService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Wyvern\Filament\Actions\PowerActions;
use Wyvern\Filament\Actions\TxAdminAction;
use Wyvern\Filament\Server\Pages\FiveM\Concerns\FiveMPage;
use Wyvern\FiveM\Layout;
use Wyvern\FiveM\ServerCfg;
use Wyvern\Minecraft\Files\MinecraftFiles;
use Wyvern\Minecraft\Reinstaller;

/**
 * server.cfg as a form. Without txAdmin, name, slots and OneSync are egg variables and the rest
 * is the file; under txAdmin everything is its deployment's server.cfg.
 */
class Config extends Page
{
    use FiveMPage;
    use InteractsWithForms;

    public const PERMISSION = 'fivem.config';

    public const LABEL = 'wyvern.fivem.config.title';

    /** Lines kept in server.cfg as written, by form field. */
    private const KEYS = [
        'project_name' => 'sets sv_projectName',
        'project_desc' => 'sets sv_projectDesc',
        'tags' => 'sets tags',
        'locale' => 'sets locale',
        'banner_detail' => 'sets banner_detail',
        'banner_connecting' => 'sets banner_connecting',
        'icon' => 'load_server_icon',
        'mysql' => 'set mysql_connection_string',
    ];

    /** Convars, written as "set name value" so Enhanced reads them too. */
    private const CONVARS = [
        'game_build' => 'sv_enforceGameBuild',
        'script_hook' => 'sv_scriptHookAllowed',
        'pure_level' => 'sv_pureLevel',
        'rcon_password' => 'rcon_password',
    ];

    private const GAME_BUILDS = [
        'fivem' => ['1604', '2060', '2189', '2372', '2545', '2612', '2699', '2802', '2944', '3095', '3258', '3323', '3407', '3570', '3717', '3751'],
        'redm' => ['1311', '1355', '1436', '1491'],
        // Enhanced runs only the latest game build, or 1 for the base game without DLC.
        'enhanced' => ['1'],
    ];

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Adjustments;

    protected static ?int $navigationSort = 2;

    protected string $view = 'wyvern.server.fivem.config';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public bool $exists = false;

    protected MinecraftFiles $files;

    protected Reinstaller $reinstaller;

    public function boot(MinecraftFiles $files, Reinstaller $reinstaller): void
    {
        $this->files = $files;
        $this->reinstaller = $reinstaller;
    }

    public function mount(): void
    {
        $cfg = $this->cfg();
        $this->exists = $cfg !== null;
        $env = $this->fivem()->env;
        $txadmin = $this->fivem()->usesTxAdmin();

        /** @var array<string, mixed> $state */
        $state = [
            'SERVER_HOSTNAME' => $txadmin ? ($cfg?->convar('sv_hostname') ?? '') : ($env['SERVER_HOSTNAME'] ?? ''),
            'MAX_PLAYERS' => (int) ($txadmin ? ($cfg?->convar('sv_maxclients') ?? 48) : ($env['MAX_PLAYERS'] ?? 48)),
            'ONESYNC' => $env['ONESYNC'] ?? 'on',
            'listed' => $cfg?->convar('sv_master1') !== '',
        ];

        foreach (self::KEYS as $field => $key) {
            $state[$field] = $cfg?->get($key) ?? '';
        }

        foreach (self::CONVARS as $field => $name) {
            $state[$field] = $cfg?->convar($name) ?? '';
        }

        $state['script_hook'] = $state['script_hook'] === '1';

        $this->form->fill($state);
    }

    public function layout(): Layout
    {
        return Layout::of($this->fivem(), $this->files);
    }

    private function cfg(): ?ServerCfg
    {
        $layout = $this->layout();
        $content = $layout->pending ? null : $this->files->read($this->server(), $layout->cfg);

        return $content === null ? null : ServerCfg::parse($content);
    }

    /** @return list<string> the variables this form edits: none under txAdmin, which reads only its own server.cfg */
    private function variables(): array
    {
        if ($this->fivem()->usesTxAdmin()) {
            return [];
        }

        return $this->fivem()->enhanced() ? ['SERVER_HOSTNAME', 'MAX_PLAYERS'] : ['SERVER_HOSTNAME', 'MAX_PLAYERS', 'ONESYNC'];
    }

    public function form(Schema $schema): Schema
    {
        $fivem = $this->fivem();
        $enhanced = $fivem->enhanced();
        $builds = self::GAME_BUILDS[$enhanced ? 'enhanced' : $fivem->game()];

        return $schema->statePath('data')->components([
            Section::make(trans('wyvern.fivem.config.groups.listing'))->columns(['default' => 1, 'lg' => 2])->schema([
                TextInput::make('SERVER_HOSTNAME')->label(trans('wyvern.fivem.config.fields.hostname'))->required()->maxLength(120)->hint('sv_hostname')->columnSpanFull(),
                TextInput::make('project_name')->label(trans('wyvern.fivem.config.fields.project_name'))->hint('sv_projectName'),
                TextInput::make('project_desc')->label(trans('wyvern.fivem.config.fields.project_desc'))->hint('sv_projectDesc'),
                TextInput::make('tags')->label(trans('wyvern.fivem.config.fields.tags'))->hint('tags')->helperText(trans('wyvern.fivem.config.help.tags')),
                TextInput::make('locale')->label(trans('wyvern.fivem.config.fields.locale'))->hint('locale')->placeholder('en-US'),
                TextInput::make('banner_detail')->label(trans('wyvern.fivem.config.fields.banner_detail'))->hint('banner_detail')->url(),
                TextInput::make('banner_connecting')->label(trans('wyvern.fivem.config.fields.banner_connecting'))->hint('banner_connecting')->url(),
                TextInput::make('icon')->label(trans('wyvern.fivem.config.fields.icon'))->hint('load_server_icon')->helperText(trans('wyvern.fivem.config.help.icon')),
                Toggle::make('listed')->label(trans('wyvern.fivem.config.fields.listed'))->helperText(trans('wyvern.fivem.config.help.listed')),
            ]),
            Section::make(trans('wyvern.fivem.config.groups.game'))->columns(['default' => 1, 'lg' => 2])->schema([
                TextInput::make('MAX_PLAYERS')->label(trans('wyvern.fivem.config.fields.slots'))->integer()->minValue(1)->maxValue(2048)->required()->hint('sv_maxclients')->helperText(trans('wyvern.fivem.config.help.slots')),
                Select::make('ONESYNC')->label('OneSync')->visible(!$enhanced && !$fivem->usesTxAdmin())->options(['on' => trans('wyvern.fivem.config.onesync.on'), 'legacy' => trans('wyvern.fivem.config.onesync.legacy'), 'off' => trans('wyvern.fivem.config.onesync.off')])->selectablePlaceholder(false)->native(false)->hint('onesync'),
                Select::make('game_build')->label(trans('wyvern.fivem.config.fields.game_build'))->hint('sv_enforceGameBuild')
                    ->options(['' => trans($enhanced ? 'wyvern.fivem.config.latest_build' : 'wyvern.fivem.config.default_build')]
                        + array_combine($builds, array_map(fn ($b) => $b === '1' ? trans('wyvern.fivem.config.base_build') : $b, $builds)))
                    ->native(false)->helperText(trans($enhanced ? 'wyvern.fivem.config.help.game_build_enhanced' : 'wyvern.fivem.config.help.game_build')),
                Select::make('pure_level')->label(trans('wyvern.fivem.config.fields.pure_level'))->hint('sv_pureLevel')->visible(!$enhanced)
                    ->options(['' => trans('wyvern.fivem.config.pure.off'), '1' => trans('wyvern.fivem.config.pure.one'), '2' => trans('wyvern.fivem.config.pure.two')])->native(false),
                Toggle::make('script_hook')->label(trans('wyvern.fivem.config.fields.script_hook'))->hint('sv_scriptHookAllowed')->visible(!$enhanced)->helperText(trans('wyvern.fivem.config.help.script_hook')),
            ]),
            Section::make(trans('wyvern.fivem.config.groups.security'))->columns(['default' => 1, 'lg' => 2])->schema([
                TextInput::make('rcon_password')->label(trans('wyvern.fivem.config.fields.rcon'))->hint('rcon_password')->password()->revealable()->helperText(trans('wyvern.fivem.config.help.rcon')),
                TextInput::make('mysql')->label(trans('wyvern.fivem.config.fields.mysql'))->hint('mysql_connection_string')->password()->revealable()->columnSpanFull()->helperText(trans('wyvern.fivem.config.help.mysql')),
            ]),
        ])->disabled(fn () => !$this->canEdit());
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);

        $state = $this->form->getState();
        $server = $this->server();

        try {
            $values = $this->reinstaller->validate($server, $state, $this->variables());
        } catch (ValidationException $e) {
            Notification::make()->title(trans('wyvern.fivem.config.failed'))->body($e->validator->errors()->first())->danger()->send();

            return;
        }

        $cfg = $this->cfg() ?? ServerCfg::parse('');

        foreach (self::KEYS as $field => $key) {
            $value = (string) ($state[$field] ?? '');

            // An empty field is a line that should not be there at all.
            if ($value === '') {
                $cfg->remove($key);
            } elseif ($value !== ($cfg->get($key) ?? '')) {
                $cfg->set($key, $value);
            }
        }

        if (array_key_exists('script_hook', $state)) {
            $state['script_hook'] = $state['script_hook'] ? '1' : '';
        }

        foreach (self::CONVARS as $field => $name) {
            // Hidden on Enhanced: leave whatever the file has.
            if (!array_key_exists($field, $state)) {
                continue;
            }

            $value = (string) $state[$field];

            if ($value === '') {
                $cfg->removeConvar($name);
            } elseif ($value !== ($cfg->convar($name) ?? '')) {
                $cfg->setConvar($name, $value);
            }
        }

        // Under txAdmin the name and slots live only in its server.cfg.
        if ($this->fivem()->usesTxAdmin()) {
            $cfg->setConvar('sv_hostname', (string) $state['SERVER_HOSTNAME']);
            $cfg->setConvar('sv_maxclients', (string) (int) $state['MAX_PLAYERS']);
        }

        // An empty sv_master1 keeps the server out of the public list.
        if ($state['listed'] ?? true) {
            $cfg->removeConvar('sv_master1');
        } else {
            $cfg->setConvar('sv_master1', '');
        }

        if ($values !== []) {
            $this->reinstaller->apply($server, $values, null, false);
        }

        $this->files->write($server, $this->layout()->cfg, $cfg->render());
        $this->dispatch('wyvern-form-saved');

        Activity::event('server:wyvern.fivem.config')->log();

        Notification::make()
            ->title(trans('wyvern.properties.notifications.saved'))
            ->body(trans('wyvern.properties.notifications.restart'))
            ->success()
            ->send();
    }

    /** Creates a MariaDB database for ESX or QBCore and points mysql_connection_string at it. */
    public function databaseAction(): Action
    {
        return Action::make('database')
            ->label(trans('wyvern.fivem.config.database.action'))
            ->icon(TablerIcon::Database)
            ->color('gray')
            ->button()
            ->visible(fn () => $this->exists && $this->canEdit()
                && blank($this->data['mysql'] ?? null)
                && (user()?->can(SubuserPermission::DatabaseCreate, $this->server()) ?? false))
            ->requiresConfirmation()
            ->modalHeading(trans('wyvern.fivem.config.database.heading'))
            ->modalDescription(trans('wyvern.fivem.config.database.description'))
            ->action(function (DeployServerDatabaseService $deploy) {
                $server = $this->server();

                try {
                    $database = $deploy->handle($server, ['database' => 'fivem', 'remote' => '%']);
                } catch (\Throwable $e) {
                    Notification::make()->title(trans('wyvern.fivem.config.database.failed'))->body($e->getMessage())->danger()->send();

                    return;
                }

                // Database::address() gives the host's display name, not its address.
                $url = sprintf('mysql://%s:%s@%s:%d/%s?charset=utf8mb4',
                    rawurlencode($database->username), rawurlencode($database->password), $database->host->host, $database->host->port, $database->database);

                $cfg = $this->cfg() ?? ServerCfg::parse('');
                $cfg->set('set mysql_connection_string', $url);
                $this->files->write($server, $this->layout()->cfg, $cfg->render());
                $this->data['mysql'] = $url;

                Notification::make()
                    ->title(trans('wyvern.fivem.config.database.done', ['name' => $database->database]))
                    ->body(trans('wyvern.fivem.config.database.done_body'))
                    ->success()
                    ->send();
            });
    }

    public function canEdit(): bool
    {
        return user()?->can(SubuserPermission::FileUpdate, $this->server()) ?? false;
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label(trans('wyvern.properties.save'))
            ->icon(TablerIcon::DeviceFloppy)
            ->button()
            ->keyBindings(['mod+s'])
            ->visible(fn () => $this->exists && $this->canEdit())
            ->action(fn () => $this->save());
    }

    /** @return array<Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [$this->saveAction(), $this->databaseAction(), TxAdminAction::make(), PowerActions::group()];
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('wyvern.navigation.game');
    }
}
