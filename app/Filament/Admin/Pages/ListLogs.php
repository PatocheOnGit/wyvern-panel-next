<?php

namespace App\Filament\Admin\Pages;

use App\Enums\TablerIcon;
use Boquizo\FilamentLogViewer\Actions\DeleteAction;
use Boquizo\FilamentLogViewer\Actions\DownloadAction;
use Boquizo\FilamentLogViewer\Actions\ViewLogAction;
use Boquizo\FilamentLogViewer\FilamentLogViewerPlugin;
use Boquizo\FilamentLogViewer\Pages\ListLogs as BaseListLogs;
use Boquizo\FilamentLogViewer\Tables\Columns\LevelColumn;
use Boquizo\FilamentLogViewer\Tables\Columns\NameColumn;
use Boquizo\FilamentLogViewer\UseCases\ParseDateUseCase;
use Boquizo\FilamentLogViewer\Utils\Level;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListLogs extends BaseListLogs
{
    protected string $view = 'filament.components.list-logs';

    public function getHeading(): string|null|Htmlable
    {
        return trans('admin/log.navigation.panel_logs');
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->emptyStateHeading(trans('admin/log.empty_table'))
            ->emptyStateIcon(TablerIcon::Check)
            ->toolbarActions([
                BulkActionGroup::make([
                    self::exclude_downloadBulkAction(),
                    self::exclude_deleteBulkAction(),
                ]),
            ])
            ->columns([
                NameColumn::make('date'),
                LevelColumn::make(Level::ALL)
                    ->tooltip(trans('admin/log.total_logs')),
                LevelColumn::make(Level::Error)
                    ->tooltip(trans('admin/log.error')),
                LevelColumn::make(Level::Warning)
                    ->tooltip(trans('admin/log.warning')),
                LevelColumn::make(Level::Notice)
                    ->tooltip(trans('admin/log.notice')),
                LevelColumn::make(Level::Info)
                    ->tooltip(trans('admin/log.info')),
                LevelColumn::make(Level::Debug)
                    ->tooltip(trans('admin/log.debug')),
            ])
            ->recordActions([
                ViewLogAction::make()
                    ->icon(TablerIcon::FileDescription)->iconButton(),
                DownloadAction::make()
                    ->tooltip(function (array $record): string {
                        return trans('filament-log-viewer::log.table.actions.download.label', ['log' => ParseDateUseCase::execute((string) ($record['date'] ?? ''))]);
                    })
                    ->icon(TablerIcon::FileDownload)->iconButton(),
                DeleteAction::make()
                    ->icon(TablerIcon::Trash)->iconButton(),
            ]);
    }

    private static function exclude_downloadBulkAction(): BulkAction
    {
        return BulkAction::make('exclude_download')
            ->label(trans('filament-log-viewer::log.table.actions.download.bulk.label'))
            ->icon(TablerIcon::FileZip)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(trans('filament-log-viewer::log.table.actions.download.bulk.label'))
            ->failureNotificationTitle(trans('filament-log-viewer::log.table.actions.download.bulk.error'))
            ->action(self::exclude_downloadBulkAction_getAction(...));
    }

    /**
     * @param  Collection<array-key, array<string, mixed>>  $records
     */
    private static function exclude_downloadBulkAction_getAction(
        BulkAction $action,
        Collection $records,
    ): ?BinaryFileResponse {
        try {
            $logs = $records->pluck('date')->all();

            return FilamentLogViewerPlugin::make()->downloadLogs($logs);
        } catch (\Exception) {
            $action->failure();

            return null;
        }
    }

    private static function exclude_deleteBulkAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make('exclude_delete')
            ->modalHeading(trans('filament-log-viewer::log.table.actions.delete.bulk.label'))
            ->action(function (DeleteBulkAction $action) {
                $action->process(function (Collection $records): void {
                    /** @var array<string, mixed> $record */
                    foreach ($records as $record) {
                        FilamentLogViewerPlugin::make()->deleteLog((string) ($record['date'] ?? ''));
                    }
                });

                $action->success();
            });
    }
}
