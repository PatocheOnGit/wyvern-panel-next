@php
    $shortcuts = $this->getShortcuts();
@endphp

<div class="wy-shortcuts">
    @foreach ($shortcuts as $shortcut)
        <a href="{{ $shortcut['url'] }}"
           target="_blank"
           rel="noopener noreferrer"
           class="wy-shortcut wy-shortcut-{{ $shortcut['tone'] ?? 'accent' }}">

            <span class="wy-shortcut-icon">
                @switch($shortcut['icon'] ?? null)
                    @case('discord')
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.5 5.6A16 16 0 0 0 15.6 4.4l-.2.4a12 12 0 0 1 3.3 1.6 12.6 12.6 0 0 0-10.6 0A12 12 0 0 1 11.4 4.8l-.2-.4A16 16 0 0 0 7.3 5.6C4.4 9.9 3.6 14 4 18a16 16 0 0 0 4.8 2.4l.6-1a10.7 10.7 0 0 1-1.7-.8l.4-.3a11.4 11.4 0 0 0 9.7 0l.4.3a10.7 10.7 0 0 1-1.7.8l.6 1A16 16 0 0 0 22.8 18c.5-4.7-.7-8.7-3.3-12.4ZM9.5 15.6c-1 0-1.7-.9-1.7-2s.8-2 1.7-2 1.8.9 1.7 2c0 1.1-.8 2-1.7 2Zm5.8 0c-1 0-1.7-.9-1.7-2s.8-2 1.7-2 1.8.9 1.7 2c0 1.1-.8 2-1.7 2Z"/></svg>
                        @break
                    @case('book')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H19v15H6.5A2.5 2.5 0 0 0 4 20.5Z"/><path d="M8 7.5h7M8 11h5"/></svg>
                        @break
                    @case('pulse')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 13h4l2.5 5L14 6l2.5 7H21"/></svg>
                        @break
                    @default
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.6 5.6 6 .8-4.4 4.2 1.1 6.1L12 16.9 6.7 19.7l1.1-6.1L3.4 9.4l6-.8Z"/></svg>
                @endswitch
            </span>

            <span class="wy-shortcut-text">
                <span class="wy-shortcut-label">{{ $shortcut['label'] }}</span>
                @if (filled($shortcut['description'] ?? null))
                    <span class="wy-shortcut-description">{{ $shortcut['description'] }}</span>
                @endif
            </span>
        </a>
    @endforeach
</div>
