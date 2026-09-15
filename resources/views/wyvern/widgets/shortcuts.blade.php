<div class="wy-shortcuts">
    @foreach ($this->getShortcuts() as $shortcut)
        @php
            $id = $shortcut['id'];
            $description = trans("wyvern.shortcuts.{$id}.description");
        @endphp

        <a href="{{ $shortcut['url'] }}"
           target="_blank"
           rel="noopener noreferrer"
           class="wy-shortcut wy-shortcut-{{ $shortcut['tone'] ?? 'accent' }}">

            <span class="wy-shortcut-icon">
                <x-filament::icon :icon="$shortcut['icon']" class="h-5 w-5" />
            </span>

            <span class="wy-shortcut-text">
                <span class="wy-shortcut-label">{{ trans("wyvern.shortcuts.{$id}.label") }}</span>
                <span class="wy-shortcut-description">{{ $description }}</span>
            </span>
        </a>
    @endforeach
</div>
