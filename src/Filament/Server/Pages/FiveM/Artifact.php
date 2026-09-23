<?php

namespace Wyvern\Filament\Server\Pages\FiveM;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Size;
use Illuminate\Validation\ValidationException;
use Wyvern\Filament\Actions\PowerActions;
use Wyvern\Filament\Actions\TxAdminAction;
use Wyvern\Filament\Forms\BackupToggle;
use Wyvern\Filament\Server\Pages\FiveM\Concerns\FiveMPage;
use Wyvern\FiveM\Artifacts;
use Wyvern\Jobs\ChangeServerJob;
use Wyvern\Minecraft\Files\MinecraftFiles;
use Wyvern\Minecraft\Reinstaller;

/** Pick the FXServer artifact: a channel, or an exact build. */
class Artifact extends Page
{
    use FiveMPage;

    public const PERMISSION = SubuserPermission::SettingsReinstall;

    public const LABEL = 'wyvern.fivem.artifact.title';

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Package;

    protected static ?int $navigationSort = 1;

    protected string $view = 'wyvern.server.fivem.artifact';

    public string $choice = 'recommended';

    protected Artifacts $artifacts;

    protected Reinstaller $reinstaller;

    protected MinecraftFiles $files;

    public function boot(Artifacts $artifacts, Reinstaller $reinstaller, MinecraftFiles $files): void
    {
        $this->artifacts = $artifacts;
        $this->reinstaller = $reinstaller;
        $this->files = $files;
    }

    public function mount(): void
    {
        $this->choice = $this->fivem()->env['FIVEM_VERSION'] ?? 'recommended';
    }

    /** @return array<string, array{build: string, txadmin: ?string}> */
    public function channels(): array
    {
        return $this->artifacts->channels();
    }

    /** @return array<string, string> */
    public function builds(): array
    {
        $builds = $this->artifacts->builds();

        return array_combine($builds, array_map(fn ($b) => strtok($b, '-'), $builds));
    }

    /** @return array{game?: string, channel?: string, build?: string} */
    public function installed(): array
    {
        try {
            $data = json_decode($this->files->read($this->server(), '/.wyvern/install.json') ?? '', true);
        } catch (\Throwable) {
            $data = null;
        }

        return is_array($data) ? $data : [];
    }

    public function select(string $choice): void
    {
        $this->choice = $choice;
    }

    public function installAction(): Action
    {
        return Action::make('install')
            ->label(trans('wyvern.fivem.artifact.install'))
            ->icon(TablerIcon::Download)
            ->size(Size::Large)
            ->button()
            ->requiresConfirmation()
            ->modalHeading(trans('wyvern.version.actions.confirm_heading'))
            ->modalDescription(trans('wyvern.fivem.artifact.confirm'))
            ->schema([BackupToggle::make($this->server())])
            ->action(function (array $data) {
                $server = $this->server();

                try {
                    $values = $this->reinstaller->validate($server, ['FIVEM_VERSION' => $this->choice], ['FIVEM_VERSION']);
                } catch (ValidationException $e) {
                    Notification::make()->title(trans('wyvern.version.notifications.failed'))->body($e->validator->errors()->first())->danger()->send();

                    return;
                }

                if (($data['backup'] ?? false) && BackupToggle::available($server)) {
                    ChangeServerJob::dispatch($server, user(), $values, null, true);
                    Notification::make()->title(trans('wyvern.version.notifications.queued'))->body(trans('wyvern.version.notifications.queued_body'))->success()->send();

                    return;
                }

                try {
                    $this->reinstaller->apply($server, $values);
                } catch (\Throwable $e) {
                    Notification::make()->title(trans('wyvern.version.notifications.failed'))->body($e->getMessage())->danger()->send();

                    return;
                }

                Activity::event('server:wyvern.fivem.artifact')->property('artifact', $this->choice)->log();

                Notification::make()
                    ->title(trans('wyvern.fivem.artifact.started', ['artifact' => $this->choice]))
                    ->body(trans('wyvern.version.notifications.started_body'))
                    ->success()
                    ->send();
            });
    }

    /** @return array<Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [TxAdminAction::make(), PowerActions::group()];
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('wyvern.navigation.software');
    }
}
