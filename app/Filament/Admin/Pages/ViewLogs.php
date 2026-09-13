<?php

namespace App\Filament\Admin\Pages;

use App\Enums\TablerIcon;
use App\Traits\ResolvesRecordDate;
use Boquizo\FilamentLogViewer\Actions\BackAction;
use Boquizo\FilamentLogViewer\Actions\DeleteAction;
use Boquizo\FilamentLogViewer\Actions\DownloadAction;
use Boquizo\FilamentLogViewer\Pages\ViewLog as BaseViewLog;

class ViewLogs extends BaseViewLog
{
    use ResolvesRecordDate;

    public function getHeaderActions(): array
    {
        return [
            BackAction::make()
                ->tooltip(trans('filament-log-viewer::log.table.actions.close.label'))
                ->icon(TablerIcon::ArrowLeft)->iconButton(),
            DeleteAction::make(withTooltip: true)
                ->icon(TablerIcon::Trash)->iconButton(),
            DownloadAction::make(withTooltip: true)
                ->icon(TablerIcon::FileDownload)->iconButton(),
        ];
    }
}
