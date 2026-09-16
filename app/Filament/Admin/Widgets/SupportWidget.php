<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\TablerIcon;
use Exception;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupportWidget extends FormWidget
{
    protected static ?int $sort = 3;

    /**
     * @throws Exception
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(trans('admin/dashboard.sections.intro-support.heading'))
                    ->icon(TablerIcon::HeartFilled)
                    ->iconColor('danger')
                    ->collapsible()
                    // Collapsed by default. An operations dashboard opens on what is
                    // wrong and how much room is left; a donation appeal and a link to
                    // the docs are worth keeping and not worth the fold. persistCollapsed
                    // still remembers anyone who opens it.
                    ->collapsed()
                    ->persistCollapsed()
                    ->schema([
                        TextEntry::make('info')
                            ->hiddenLabel()
                            ->state(trans('admin/dashboard.sections.intro-support.content')),
                        TextEntry::make('extra')
                            ->hiddenLabel()
                            ->state(trans('admin/dashboard.sections.intro-support.extra_note')),
                    ])
                    ->headerActions([
                        Action::make('db_donate')
                            ->label(trans('admin/dashboard.sections.intro-support.button_donate'))
                            ->icon(TablerIcon::Cash)
                            ->url('https://github.com/PatocheOnGit/wyvern-panel', true)
                            ->color('success'),
                    ]),
            ]);
    }
}
