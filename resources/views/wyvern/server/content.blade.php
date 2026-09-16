@php
    $profile = $this->profile();
    $types = $this->availableTypes();
    $current = $this->currentType();
    $results = $this->results();
@endphp

<x-filament-panels::page>
    <div class="wy-content">

        <div class="wy-content-bar">
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
                       placeholder="{{ trans('wyvern.content.search_placeholder') }}">
            </div>

            <div class="wy-content-scope">
                <span>{{ $profile->loader?->label() }}</span>
                <span class="wy-content-dot">·</span>
                <span>{{ $profile->gameVersion ?? trans('wyvern.content.any_version') }}</span>
            </div>
        </div>

        @if ($profile->gameVersion === null)
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
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($current === \Wyvern\Content\ContentType::Modpack)
            <p class="wy-content-note">
                <x-filament::icon icon="tabler-info-circle" class="h-4 w-4" />
                {{ trans('wyvern.content.modpack_note') }}
            </p>
        @endif
    </div>
</x-filament-panels::page>
