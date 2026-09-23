@php
    $profile = $this->profile();
    $installedMode = $this->view_mode === 'installed';
    $directory = $this->contentDirectory();
@endphp

<x-filament-panels::page>
    <div class="wy-content">

        <div class="wy-content-tabs wy-content-modes">
            <button type="button" wire:click="selectView('browse')" @class(['wy-content-tab', 'wy-content-tab-on' => ! $installedMode])>
                {{ trans('wyvern.content.modes.browse') }}
            </button>
            @if ($directory !== null)
                <button type="button" wire:click="selectView('installed')" @class(['wy-content-tab', 'wy-content-tab-on' => $installedMode])>
                    {{ trans('wyvern.content.modes.installed') }}
                </button>
            @endif
        </div>

        @if ($installedMode && $directory !== null)
            @php
                $files = $this->installedFiles();
                $modpack = $this->installedModpack();
            @endphp

            <div class="wy-content-bar">
                <p class="wy-content-summary-line">
                    {{ trans_choice('wyvern.content.installed.count', count($files), ['count' => count($files), 'directory' => $directory . '/']) }}
                </p>
                <button type="button" class="wy-content-install" wire:click="checkUpdates" wire:loading.attr="disabled" wire:target="checkUpdates">
                    <x-filament::icon icon="tabler-refresh" class="h-3.5 w-3.5" wire:loading.remove wire:target="checkUpdates" />
                    <x-filament::loading-indicator class="h-3.5 w-3.5" wire:loading wire:target="checkUpdates" />
                    {{ trans('wyvern.content.installed.check_updates') }}
                </button>
            </div>

            @if ($modpack)
                <p class="wy-content-note">
                    <x-filament::icon icon="tabler-packages" class="h-4 w-4" />
                    {{ trans('wyvern.content.installed.modpack', ['name' => $modpack['name'] ?? '?', 'version' => $modpack['version_name'] ?? '']) }}
                </p>
            @endif

            @if ($files === [])
                <div class="wy-content-empty">
                    <x-filament::icon icon="tabler-mood-empty" class="h-8 w-8" />
                    <p>{{ trans('wyvern.content.installed.empty', ['directory' => $directory . '/']) }}</p>
                </div>
            @else
                <ul class="wy-player-list">
                    @foreach ($files as $file)
                        @php $update = $this->updates[$file['key']] ?? null; @endphp
                        <li class="wy-player-row" wire:key="file-{{ $file['key'] }}">
                            <span class="wy-player-head wy-content-file-icon">
                                @if ($file['icon'])
                                    <img src="{{ $file['icon'] }}" alt="" loading="lazy">
                                @else
                                    <x-filament::icon icon="tabler-package" class="h-4 w-4" />
                                @endif
                            </span>

                            <div class="wy-player-text">
                                <span class="wy-player-name">
                                    {{ $file['title'] ?? $file['name'] }}
                                    @if (! $file['enabled'])
                                        <span class="wy-player-badge wy-player-badge-muted">{{ trans('wyvern.content.installed.disabled') }}</span>
                                    @endif
                                    @if ($file['record']['modpack'] ?? false)
                                        <span class="wy-player-badge wy-player-badge-muted">{{ trans('wyvern.content.installed.from_modpack') }}</span>
                                    @endif
                                    @if ($file['record'] === null)
                                        <span class="wy-player-badge wy-player-badge-muted">{{ trans('wyvern.content.installed.manual') }}</span>
                                    @endif
                                </span>
                                <span class="wy-player-meta">
                                    {{ $file['name'] }}
                                    @if ($file['record']['version_name'] ?? null)
                                        · {{ $file['record']['version_name'] }}
                                    @endif
                                </span>
                            </div>

                            <div class="wy-player-actions">
                                @if ($update)
                                    <button type="button" class="wy-row-button wy-row-button-accent"
                                            wire:click="updateFile(@js($file['path']))"
                                            wire:loading.attr="disabled">
                                        {{ trans('wyvern.content.installed.update_to', ['version' => $update['versionName']]) }}
                                    </button>
                                @endif
                                <button type="button" class="wy-row-button"
                                        wire:click="toggleFile(@js($file['path']))"
                                        wire:loading.attr="disabled">
                                    {{ trans($file['enabled'] ? 'wyvern.content.installed.disable' : 'wyvern.content.installed.enable') }}
                                </button>
                                <button type="button" class="wy-row-button wy-row-button-danger"
                                        wire:click="deleteFile(@js($file['path']))"
                                        wire:confirm="{{ trans('wyvern.content.installed.delete_confirm', ['file' => $file['name']]) }}"
                                        wire:loading.attr="disabled">
                                    {{ trans('wyvern.content.installed.delete') }}
                                </button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <p class="wy-page-footnote">{{ trans('wyvern.content.installed.restart_note') }}</p>
        @else
            @php
                $types = $this->availableTypes();
                $current = $this->currentType();
                $results = $this->results();
                $sources = $this->sources();
                $source = $this->currentSource();
                $gameVersion = $this->gameVersion();
                $isModpack = $current === \Wyvern\Content\ContentType::Modpack;
            @endphp

            <div class="wy-content-bar">
                @if (count($sources) > 1)
                    <div class="wy-content-tabs">
                        @foreach ($sources as $option)
                            <button type="button"
                                    wire:click="selectSource('{{ $option->key() }}')"
                                    @class(['wy-content-tab', 'wy-content-tab-on' => $option->key() === $source?->key()])>
                                {{ $option->label() }}
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="wy-content-tabs">
                    @foreach ($types as $type)
                        <button type="button"
                                wire:click="selectType('{{ $type->value }}')"
                                @class(['wy-content-tab', 'wy-content-tab-on' => $type === $current])>
                            {{ $type->label() }}
                        </button>
                    @endforeach
                </div>

                <div class="wy-content-search">
                    <x-filament::icon icon="tabler-search" class="h-4 w-4" />
                    <input type="text"
                           wire:model.live.debounce.400ms="search"
                           placeholder="{{ trans('wyvern.content.search_placeholder', ['source' => $source?->label() ?? 'Modrinth']) }}">
                </div>

                <select class="wy-select wy-select-compact" wire:model.live="sort" aria-label="{{ trans('wyvern.content.sort.label') }}">
                    @foreach (\Wyvern\Content\Contracts\ContentSource::SORTS as $sortKey)
                        <option value="{{ $sortKey }}">{{ trans("wyvern.content.sort.$sortKey") }}</option>
                    @endforeach
                </select>

                @php $categories = $this->categories(); @endphp
                @if ($categories !== [])
                    <select class="wy-select wy-select-compact" wire:model.live="category" aria-label="{{ trans('wyvern.content.category') }}">
                        <option value="">{{ trans('wyvern.content.all_categories') }}</option>
                        @foreach ($categories as $slug => $label)
                            <option value="{{ $slug }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @endif

                <div class="wy-content-scope">
                    <span>{{ $profile->loader?->label() }}</span>
                    <span class="wy-content-dot">·</span>
                    <span>{{ $gameVersion ?? trans('wyvern.content.any_version') }}</span>
                </div>
            </div>

            @if ($gameVersion === null && ! $isModpack)
                <p class="wy-content-note">
                    <x-filament::icon icon="tabler-info-circle" class="h-4 w-4" />
                    {{ trans('wyvern.content.unpinned') }}
                </p>
            @endif

            @if ($results === [])
                <div class="wy-content-empty">
                    <x-filament::icon icon="tabler-mood-empty" class="h-8 w-8" />
                    <p>{{ trans('wyvern.content.empty') }}</p>
                </div>
            @else
                <div class="wy-content-grid" wire:loading.class="wy-content-busy">
                    @foreach ($results as $project)
                        <div class="wy-content-card" wire:key="{{ $project->source }}-{{ $project->id }}">
                            <div class="wy-content-icon">
                                @if ($project->iconUrl)
                                    <img src="{{ $project->iconUrl }}" alt="">
                                @else
                                    <x-filament::icon icon="tabler-package" class="h-6 w-6" />
                                @endif
                            </div>

                            <div class="wy-content-text">
                                <div class="wy-content-head">
                                    <span class="wy-content-name">{{ $project->title }}</span>
                                    @if ($project->author)
                                        <span class="wy-content-author">{{ $project->author }}</span>
                                    @endif
                                </div>
                                <p class="wy-content-summary">{{ $project->summary }}</p>
                                <div class="wy-content-foot">
                                    <span class="wy-content-downloads">{{ $project->downloadsForHumans() }} {{ trans('wyvern.content.downloads') }}</span>

                                    @if ($isModpack)
                                        <button type="button"
                                                class="wy-content-install"
                                                wire:click="mountAction('installModpack', { project: @js($project->id), title: @js($project->title) })">
                                            <x-filament::icon icon="tabler-download" class="h-3.5 w-3.5" />
                                            {{ trans('wyvern.content.install') }}
                                        </button>
                                    @else
                                        <button type="button"
                                                class="wy-content-versions"
                                                wire:click="mountAction('chooseVersion', { project: @js($project->id), title: @js($project->title) })">
                                            {{ trans('wyvern.content.versions.action') }}
                                        </button>
                                        <button type="button"
                                                class="wy-content-install"
                                                wire:click="install('{{ $project->id }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="install('{{ $project->id }}')">
                                            <x-filament::icon icon="tabler-download" class="h-3.5 w-3.5"
                                                              wire:loading.remove wire:target="install('{{ $project->id }}')" />
                                            <x-filament::loading-indicator class="h-3.5 w-3.5"
                                                                          wire:loading wire:target="install('{{ $project->id }}')" />
                                            {{ trans('wyvern.content.install') }}
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($isModpack)
                <p class="wy-content-note">
                    <x-filament::icon icon="tabler-info-circle" class="h-4 w-4" />
                    {{ trans('wyvern.content.modpack_note') }}
                </p>
            @endif
        @endif
    </div>
</x-filament-panels::page>
