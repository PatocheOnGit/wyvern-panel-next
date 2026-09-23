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
use Wyvern\FiveM\FiveMServer;
use Wyvern\FiveM\ServerCfg;
use Wyvern\Minecraft\Files\MinecraftFiles;
use Wyvern\Minecraft\Reinstaller;

/** server.cfg as a form. Name, slots and OneSync are egg variables, the rest is the file. */
class Config extends Page
{
    use FiveMPage;
    use InteractsWithForms;

    public const PERMISSION = 'fivem.config';

    public const LABEL = 'wyvern.fivem.config.title';

    /** Lines Wings rewrites from a variable on every start. */
    private const VARIABLES = ['SERVER_HOSTNAME', 'MAX_PLAYERS', 'ONESYNC'];

    /** Keys kept in server.cfg, by form field. */
    private const KEYS = [
        'project_name' => 'sets sv_projectName',
        'project_desc' => 'sets sv_projectDesc',
        'tags' => 'sets tags',
        'locale' => 'sets locale',
        'banner_detail' => 'sets banner_detail',
        'banner_connecting' => 'sets banner_connecting',
        'icon' => 'load_server_icon',
        'game_build' => 'sv_enforceGameBuild',
        'script_hook' => 'sv_scriptHookAllowed',
        'pure_level' => 'sv_pureLevel',
        'endpoint_privacy' => 'sv_endpointprivacy',
        'rcon_password' => 'rcon_password',
        'mysql' => 'set mysql_connection_string',
    ];

    private const GAME_BUILDS = [
        'fivem' => ['1604', '2060', '2189', '2372', '2545', '2612', '2699', '2802', '2944', '3095', '3258', '3323', '3407', '3570'],
        'redm' => ['1311', '1355', '1436', '1491'],
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

        /** @var array<string, mixed> $state */
        $state = [
            'SERVER_HOSTNAME' => $env['SERVER_HOSTNAME'] ?? '',
            'MAX_PLAYERS' => (int) ($env['MAX_PLAYERS'] ?? 48),
            'ONESYNC' => $env['ONESYNC'] ?? 'on',
            'listed' => $cfg?->get('sv_master1') !== '',
        ];

        foreach (self::KEYS as $field => $key) {
            $state[$field] = $cfg?->get($key) ?? '';
        }

        $state['script_hook'] = ($state['script_hook'] ?? '0') === '1';
        $state['endpoint_privacy'] = ($state['endpoint_privacy'] ?? 'true') !== 'false';

        $this->form->fill($state);
    }

    private function cfg(): ?ServerCfg
    {
        $content = $this->files->read($this->server(), FiveMServer::CFG);

        return $content === null ? null : ServerCfg::parse($content);
    }

    public function form(Schema $schema): Schema
    {
        $builds = self::GAME_BUILDS[$this->fivem()->game()];

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
                Select::make('ONESYNC')->label('OneSync')->options(['on' => trans('wyvern.fivem.config.onesync.on'), 'legacy' => trans('wyvern.fivem.config.onesync.legacy'), 'off' => trans('wyvern.fivem.config.onesync.off')])->selectablePlaceholder(false)->native(false)->hint('onesync'),
                Select::make('game_build')->label(trans('wyvern.fivem.config.fields.game_build'))->hint('sv_enforceGameBuild')
                    ->options(['' => trans('wyvern.fivem.config.default_build')] + array_combine($builds, $builds))
                    ->native(false)->helperText(trans('wyvern.fivem.config.help.game_build')),
                Select::make('pure_level')->label(trans('wyvern.fivem.config.fields.pure_level'))->hint('sv_pureLevel')
                    ->options(['' => trans('wyvern.fivem.config.pure.off'), '1' => trans('wyvern.fivem.config.pure.one'), '2' => trans('wyvern.fivem.config.pure.two')])->native(false),
                Toggle::make('script_hook')->label(trans('wyvern.fivem.config.fields.script_hook'))->hint('sv_scriptHookAllowed')->helperText(trans('wyvern.fivem.config.help.script_hook')),
            ]),
            Section::make(trans('wyvern.fivem.config.groups.security'))->columns(['default' => 1, 'lg' => 2])->schema([
                TextInput::make('rcon_password')->label(trans('wyvern.fivem.config.fields.rcon'))->hint('rcon_password')->password()->revealable()->helperText(trans('wyvern.fivem.config.help.rcon')),
                Toggle::make('endpoint_privacy')->label(trans('wyvern.fivem.config.fields.endpoint_privacy'))->hint('sv_endpointprivacy')->helperText(trans('wyvern.fivem.config.help.endpoint_privacy')),
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
            $values = $this->reinstaller->validate($server, $state, self::VARIABLES);
        } catch (ValidationException $e) {
            Notification::make()->title(trans('wyvern.fivem.config.failed'))->body($e->validator->errors()->first())->danger()->send();

            return;
        }

        $cfg = $this->cfg() ?? ServerCfg::parse('');
        $state['script_hook'] = ($state['script_hook'] ?? false) ? '1' : '0';
        $state['endpoint_privacy'] = ($state['endpoint_privacy'] ?? true) ? 'true' : 'false';

        foreach (self::KEYS as $field => $key) {
            $value = (string) ($state[$field] ?? '');
            $optional = !in_array($field, ['script_hook', 'endpoint_privacy'], true);

            // An empty optional field is a line that should not be there at all.
            if ($value === '' && $optional) {
                $cfg->remove($key);
            } elseif ($value !== ($cfg->get($key) ?? '')) {
                $cfg->set($key, $value);
            }
        }

        // An empty sv_master1 keeps the server out of the public list.
        if ($state['listed'] ?? true) {
            $cfg->remove('sv_master1');
        } else {
            $cfg->set('sv_master1', '');
        }

        $this->reinstaller->apply($server, $values, null, false);
        $this->files->write($server, FiveMServer::CFG, $cfg->render());
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
            ->visible(fn () => $this->canEdit()
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
                $this->files->write($server, FiveMServer::CFG, $cfg->render());
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
