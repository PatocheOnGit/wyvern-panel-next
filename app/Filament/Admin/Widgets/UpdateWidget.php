<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\TablerIcon;
use App\Services\Helpers\SoftwareVersionService;
use Exception;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UpdateWidget extends FormWidget
{
    protected static ?int $sort = 0;

    private SoftwareVersionService $softwareVersionService;

    public function mount(SoftwareVersionService $softwareVersionService): void
    {
        $this->softwareVersionService = $softwareVersionService;
    }

    /**
     * @throws Exception
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            match ($this->softwareVersionService->panelUpdateState()) {
                SoftwareVersionService::UPDATE_CURRENT => $this->current(),
                SoftwareVersionService::UPDATE_AVAILABLE => $this->available(),
                default => $this->unknown(),
            },
        ]);
    }

    private function current(): Section
    {
        return Section::make(trans('admin/dashboard.sections.intro-no-update.heading'))
            ->icon(TablerIcon::CircleCheck)
            ->iconColor('success')
            ->schema([
                TextEntry::make('info')
                    ->hiddenLabel()
                    ->state(trans('admin/dashboard.sections.intro-no-update.content', [
                        'version' => $this->softwareVersionService->currentPanelVersion(),
                    ])),
            ]);
    }

    private function available(): Section
    {
        return Section::make(trans('admin/dashboard.sections.intro-update-available.heading'))
            ->icon(TablerIcon::InfoCircle)
            ->iconColor('warning')
            ->schema([
                TextEntry::make('info')
                    ->hiddenLabel()
                    ->state(trans('admin/dashboard.sections.intro-update-available.content', [
                        'latestVersion' => $this->softwareVersionService->latestPanelVersion(),
                    ])),
                Section::make(trans('admin/dashboard.sections.intro-update-available.button_changelog'))
                    ->icon(TablerIcon::Script)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('changelog')
                            ->hiddenLabel()
                            ->state($this->softwareVersionService->latestPanelVersionChangelog())
                            ->markdown(),
                    ]),
            ])
            ->headerActions([
                Action::make('db_update')
                    ->label(trans('wyvern.updates.open_repository'))
                    ->icon(TablerIcon::BrandGithub)
                    ->url($this->softwareVersionService->updateRepositoryUrl(), true)
                    ->color('warning'),
            ]);
    }

    /**
     * The state upstream had no room for.
     *
     * isLatestPanel() returned a boolean, and a boolean cannot say "I could not find
     * out" — so a canary build reported itself up to date without ever having asked.
     * Saying which of the two reasons applies is the difference between a status and a
     * guess, and it names the fix in the one case that has one.
     */
    private function unknown(): Section
    {
        $isCanary = config('app.version') === 'canary';

        return Section::make(trans('wyvern.updates.unknown_heading'))
            ->icon(TablerIcon::GitCommit)
            ->iconColor('gray')
            ->schema([
                TextEntry::make('info')
                    ->hiddenLabel()
                    ->state($isCanary
                        ? trans('wyvern.updates.unknown_canary', [
                            'version' => $this->softwareVersionService->currentPanelVersion(),
                            'repository' => $this->softwareVersionService->updateRepository(),
                        ])
                        : trans('wyvern.updates.unknown_unreachable', [
                            'repository' => $this->softwareVersionService->updateRepository(),
                        ])),
            ])
            ->headerActions([
                Action::make('db_repository')
                    ->label(trans('wyvern.updates.open_repository'))
                    ->icon(TablerIcon::BrandGithub)
                    ->url($this->softwareVersionService->updateRepositoryUrl(), true)
                    ->color('gray'),
            ]);
    }
}
