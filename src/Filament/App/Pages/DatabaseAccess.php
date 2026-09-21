<?php

namespace Wyvern\Filament\App\Pages;

use App\Enums\TablerIcon;
use App\Models\Database;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use PDO;
use PDOException;
use Wyvern\Http\ClaimPhpMyAdminSignon;

/**
 * The way in to phpMyAdmin.
 *
 * phpMyAdmin has no idea who is signed in to the panel and no idea which databases belong
 * to whom, so on its own it can only offer a bare MySQL login — which means handing every
 * user a second credential and hoping they only touch their own data. This page is the
 * missing middle: it lists the databases the visitor can actually reach through the panel,
 * takes the password for the one they pick, and hands phpMyAdmin a session already logged
 * in as that database's own MySQL user.
 *
 * Three things follow from that, and each is deliberate:
 *
 *  - The password is asked for, never taken from the database. The panel does store it,
 *    encrypted, and could sign people in without asking — but then any open panel tab
 *    would be an open shell on every database its owner has, and a borrowed laptop would
 *    be as good as a stolen password.
 *  - Scoping is left to MySQL. The panel grants each database user rights over its own
 *    schema and nothing else, so phpMyAdmin shows exactly that much without being
 *    configured to hide anything. A UI-level filter would be a second, weaker copy of a
 *    rule the database already enforces.
 *  - The handover is bound to the panel session, not to a URL. phpMyAdmin asks its
 *    signon source for credentials on every single request, so anything consumed by
 *    reading it — a one-time token, say — works for exactly one page and then throws the
 *    visitor back here. The selection is stored against the session instead, which is the
 *    session phpMyAdmin is being signed on from.
 *
 * @property Schema $form
 */
class DatabaseAccess extends Page
{
    use InteractsWithForms;

    /**
     * How long a selection stays current, in minutes.
     *
     * Matched to the panel's own session lifetime: the selection is meaningless without
     * the session that made it, so outliving it would only leave credentials in a cache
     * with nobody able to reach them.
     */
    private const SELECTION_TTL = 120;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Database;

    protected static ?string $slug = 'pma';

    protected string $view = 'wyvern.app.database-access';

    /**
     * Not in the sidebar. It is reached from a server's Databases page, where the visitor
     * already knows which database they mean; a top-level entry would invite people to
     * arrive here with no idea what to choose.
     */
    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function getTitle(): string
    {
        return trans('wyvern.database_access.title');
    }

    public static function canAccess(): bool
    {
        // Signed in is the whole requirement. Which databases a visitor may open is
        // decided per database below, and enforced by MySQL after that.
        //
        // The config flag is not a permission: it says whether this install has a
        // phpMyAdmin behind /pma at all. Without one, this page would hand people a button
        // that leads to a 404.
        return user() !== null && config('wyvern.phpmyadmin.enabled');
    }

    public function mount(): void
    {
        // Arriving from a server's Databases page, where the visitor already said which
        // database they meant. Re-checked against the accessible list rather than trusted,
        // since it comes from the query string.
        $requested = request()->integer('database');

        $this->form->fill(
            $this->availableDatabases()->contains('id', $requested)
                ? ['database_id' => $requested]
                : [],
        );
    }

    /**
     * Every database this visitor can reach, keyed by id.
     *
     * accessibleServers() is the panel's own answer to "what may this user see" — owner,
     * subuser, or an admin with rights over the node — so the list cannot drift from what
     * the rest of the panel shows.
     *
     * @return Collection<int, Database>
     */
    public function availableDatabases()
    {
        $user = user();

        if ($user === null) {
            return collect();
        }

        return Database::query()
            ->with(['host', 'server'])
            ->whereIn('server_id', $user->accessibleServers()->select('servers.id'))
            ->get()
            ->sortBy(fn (Database $database) => $database->server?->name . $database->database)
            ->values();
    }

    public function form(Schema $schema): Schema
    {
        $databases = $this->availableDatabases();

        return $schema
            ->statePath('data')
            ->components([
                Select::make('database_id')
                    ->label(trans('wyvern.database_access.fields.database'))
                    ->options($databases->mapWithKeys(fn (Database $database) => [
                        $database->id => $database->server?->name
                            ? $database->server->name . ' — ' . $database->database
                            : $database->database,
                    ]))
                    ->helperText(fn (Get $get) => $this->hintFor($get('database_id')))
                    ->native(false)
                    ->searchable($databases->count() > 8)
                    ->live()
                    ->required(),

                TextInput::make('password')
                    ->label(trans('wyvern.database_access.fields.password'))
                    ->helperText(trans('wyvern.database_access.fields.password_help'))
                    ->password()
                    ->revealable()
                    ->required(),
            ]);
    }

    /**
     * The username to sign in as, shown under the picker.
     *
     * People reasonably expect to type their panel password here; saying whose password is
     * wanted, before they type the wrong one, is cheaper than an error afterwards.
     */
    private function hintFor(mixed $databaseId): ?string
    {
        if (blank($databaseId)) {
            return null;
        }

        $database = $this->availableDatabases()->firstWhere('id', (int) $databaseId);

        if ($database === null) {
            return null;
        }

        return trans('wyvern.database_access.fields.database_help', [
            'username' => $database->username,
            'host' => $database->host?->host . ':' . $database->host?->port,
        ]);
    }

    public function openAction(): Action
    {
        return Action::make('open')
            ->label(trans('wyvern.database_access.actions.open'))
            ->icon(TablerIcon::ExternalLink)
            ->submit('open');
    }

    public function open(): void
    {
        $state = $this->form->getState();

        $database = $this->availableDatabases()->firstWhere('id', (int) $state['database_id']);

        // Re-resolved from the accessible list rather than trusted from the form: the id
        // arrives from the browser, and a select is only a suggestion.
        if ($database === null) {
            Notification::make()
                ->title(trans('wyvern.database_access.notifications.gone'))
                ->danger()
                ->send();

            return;
        }

        $host = $database->host;

        if ($host === null) {
            Notification::make()
                ->title(trans('wyvern.database_access.notifications.no_host'))
                ->danger()
                ->send();

            return;
        }

        if (!$this->credentialsWork($host->host, $host->port, $database->database, $database->username, $state['password'])) {
            Notification::make()
                ->title(trans('wyvern.database_access.notifications.refused'))
                ->body(trans('wyvern.database_access.notifications.refused_body'))
                ->danger()
                ->send();

            return;
        }

        Cache::put(
            ClaimPhpMyAdminSignon::cacheKey(user()->id),
            [
                'username' => $database->username,
                'password' => $state['password'],
                'database' => $database->database,
            ],
            now()->addMinutes(self::SELECTION_TTL),
        );

        // Straight into the database, rather than phpMyAdmin's front page — which is a
        // version number and a server collation nobody here can change, and which the
        // theme hides for that reason.
        $this->redirect('/pma/app/index.php?route=/database/structure&db=' . urlencode($database->database));
    }

    /**
     * Ask MySQL, rather than the panel, whether these credentials are good.
     *
     * The alternative is comparing against the password the panel has stored, which would
     * pass for a database whose password was changed outside the panel and fail for one
     * whose stored copy is stale. The database is the authority on its own logins.
     */
    private function credentialsWork(string $host, int $port, string $database, string $username, string $password): bool
    {
        try {
            new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s', $host, $port, $database),
                $username,
                $password,
                [
                    PDO::ATTR_TIMEOUT => 5,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ],
            );

            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
