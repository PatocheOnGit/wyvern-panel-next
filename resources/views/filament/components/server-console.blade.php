<x-filament::widget>
    @assets
    @php
        $userFont = (string) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFont);
        $userFontSize = (int) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFontSize);
        $userRows = (int) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleRows);

        $terminalPrelude = str(config('app.name'))->slug()->lower()->toString();
    @endphp
    @if($userFont !== "monospace")
        <link rel="preload" href="{{ asset("storage/fonts/{$userFont}.ttf") }}" as="font" crossorigin>
        <style>
            @font-face {
                font-family: '{{ $userFont }}';
                src: url('{{ asset("storage/fonts/{$userFont}.ttf") }}');
            }
        </style>
    @endif
    @vite(['resources/js/console.js', 'resources/css/console.css'])
    @endassets

    <div id="terminal" wire:ignore></div>

    @if ($this->authorizeSendCommand())
        <div class="flex items-center w-full border-top overflow-hidden dark:bg-gray-900"
             style="border-end-end-radius: var(--radius-xl); border-end-start-radius: var(--radius-xl);">
            <x-filament::icon
                icon="tabler-chevrons-right"
            />
            <input
                id="send-command"
                class="w-full focus:outline-none focus:ring-0 border-none dark:bg-gray-900 p-1"
                type="text"
                :readonly="{{ $this->canSendCommand() ? 'false' : 'true' }}"
                title="{{ $this->canSendCommand() ? '' : trans('server/console.command_blocked_title') }}"
                placeholder="{{ $this->canSendCommand() ? trans('server/console.command') : trans('server/console.command_blocked') }}"
                wire:model="input"
                wire:keydown.enter="enter"
                wire:keydown.up.prevent="up"
                wire:keydown.down="down"
            >
        </div>
    @endif

    @script
    <script>
        // The sixteen ANSI colours stay ANSI. A server writing "error" in red has to look
        // red whatever the panel's palette is doing, so they are not derived from the
        // Wyvern tokens — only the chrome around them is. What the theme does need is a
        // second set: the dark palette below is unreadable on a light background, and the
        // console is the one surface where a washed-out colour costs you information.
        const ansi = {
            dark: {
                black: '#1f1e1c',
                red: '#E54B4B',
                green: '#9ECE58',
                yellow: '#FAED70',
                blue: '#6FA0F5',
                magenta: '#BB80B3',
                cyan: '#2DDAFD',
                white: '#d0d0d0',
                brightBlack: 'rgba(255, 255, 255, 0.35)',
                brightRed: '#FF5370',
                brightGreen: '#C3E88D',
                brightYellow: '#FFCB6B',
                brightBlue: '#82AAFF',
                brightMagenta: '#C792EA',
                brightCyan: '#89DDFF',
                brightWhite: '#ffffff',
            },
            light: {
                black: '#1f1e1c',
                red: '#B3261E',
                green: '#2E7D32',
                yellow: '#8D6E00',
                blue: '#1A5FB4',
                magenta: '#7B3FA0',
                cyan: '#00697A',
                white: '#57534C',
                brightBlack: 'rgba(11, 10, 9, 0.45)',
                brightRed: '#C5352B',
                brightGreen: '#38893D',
                brightYellow: '#A07C00',
                brightBlue: '#2A6FD0',
                brightMagenta: '#8E4CB8',
                brightCyan: '#0A7E92',
                brightWhite: '#1f1e1c',
            },
        };

        const token = (name, fallback) =>
            getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;

        const buildTheme = () => ({
            // A real colour, not a transparent one. The old value was a 70%-alpha navy
            // baked into this file, which is why the terminal stayed blue after the panel
            // stopped being blue — but asking xterm to composite instead does not work:
            // with the WebGL renderer it paints the viewport opaque black whether the
            // theme says 'transparent' or rgba(0,0,0,0). xterm owns that canvas, so it
            // gets a colour it can paint, from the same token CSS uses for the frame.
            background: token('--wy-console-bg', '#0b0a09'),
            foreground: token('--wy-console-fg', '#ded9d0'),
            cursor: token('--wy-console-fg', '#ded9d0'),
            cursorAccent: token('--wy-console-bg', '#0b0a09'),
            selectionBackground: token('--wy-console-selection', 'rgba(91, 146, 245, 0.28)'),
            ...(document.documentElement.classList.contains('dark') ? ansi.dark : ansi.light),
        });

        let theme = buildTheme();

        let options = {
            fontSize: {{ $userFontSize }},
            fontFamily: '{{ $userFont }}, monospace',
            lineHeight: 1.2,
            disableStdin: true,
            cursorStyle: 'underline',
            cursorInactiveStyle: 'underline',
            allowTransparency: true,
            rows: {{ $userRows }},
            theme: theme
        };

        const { Terminal, FitAddon, WebLinksAddon, SearchAddon, SearchBarAddon, WebglAddon } = window.Xterm;

        const terminal = new Terminal(options);
        const fitAddon = new FitAddon();
        const webLinksAddon = new WebLinksAddon();
        const searchAddon = new SearchAddon();
        const searchAddonBar = new SearchBarAddon({ searchAddon });
        const webglAddon = new WebglAddon();
        terminal.loadAddon(fitAddon);
        terminal.loadAddon(webLinksAddon);
        terminal.loadAddon(searchAddon);
        terminal.loadAddon(searchAddonBar);
        terminal.loadAddon(webglAddon);

        terminal.open(document.getElementById('terminal'));

        // Filament's theme switcher toggles a class on <html> without reloading, and the
        // terminal keeps its colours in a JS object rather than in CSS — so it has to be
        // told. Without this, switching to light leaves a dark-tuned palette on a light
        // surface.
        new MutationObserver(() => { terminal.options.theme = buildTheme(); })
            .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        fitAddon.fit(); // Fixes SPA issues.

        window.addEventListener('load', () => {
            fitAddon.fit();
        });

        window.addEventListener('resize', () => {
            fitAddon.fit();
        });

        terminal.attachCustomKeyEventHandler((event) => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
                navigator.clipboard.writeText(terminal.getSelection());
                return false;
            } else if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
                event.preventDefault();
                searchAddonBar.show();
                return false;
            } else if (event.key === 'Escape') {
                searchAddonBar.hidden();
            }
            return true;
        });

        const TERMINAL_PRELUDE = '\u001b[1m\u001b[33m{{ $terminalPrelude }}@' + '{{ \Filament\Facades\Filament::getTenant()->name }}' + ' ~ \u001b[0m';

        const handleConsoleOutput = (line, prelude = false) =>
            terminal.writeln((prelude ? TERMINAL_PRELUDE : '') + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

        const handleTransferStatus = (status) =>
            status === 'failure' && terminal.writeln(TERMINAL_PRELUDE + 'Transfer has failed.\u001b[0m');

        const handleDaemonErrorOutput = (line) =>
            terminal.writeln(TERMINAL_PRELUDE + '\u001b[1m\u001b[41m' + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

        const handlePowerChangeEvent = (state) =>
            terminal.writeln(TERMINAL_PRELUDE + 'Server marked as ' + state + '...\u001b[0m');

        const socket = new WebSocket("{{ $this->getSocket() }}");

        socket.onerror = (event) => {
            $wire.dispatchSelf('websocket-error');
        };

        socket.onmessage = function(websocketMessageEvent) {
            let { event, args } = JSON.parse(websocketMessageEvent.data);

            switch (event) {
                case 'console output':
                case 'install output':
                    handleConsoleOutput(args[0]);
                    break;
                case 'install completed':
                    $wire.dispatch('refresh-sidebar');
                    $wire.dispatch('refresh-topbar');
                    $wire.dispatch('removeAlertBanner', { id: 'server_conflict' });
                    break;
                case 'feature match':
                    Livewire.dispatch('mount-feature', { data: args[0] });
                    break;
                case 'status':
                    handlePowerChangeEvent(args[0]);
                    $wire.dispatch('console-status', { state: args[0] });
                    break;
                case 'transfer status':
                    handleTransferStatus(args[0]);
                    break;
                case 'daemon error':
                    handleDaemonErrorOutput(args[0]);
                    break;
                case 'stats':
                    $wire.dispatchSelf('store-stats', { data: args[0] });
                    break;
                case 'auth success':
                    socket.send(JSON.stringify({
                        'event': 'send logs',
                        'args': [null]
                    }));
                    break;
                case 'token expiring':
                case 'token expired':
                    $wire.dispatchSelf('token-request');
                    break;
            }
        };

        socket.onopen = (event) => {
            $wire.dispatchSelf('token-request');
        };

        Livewire.on('setServerState', ({ state, uuid }) => {
            const serverUuid = "{{ $this->server->uuid }}";
            if (uuid !== serverUuid) {
                return;
            }

            socket.send(JSON.stringify({
                'event': 'set state',
                'args': [state]
            }));
        });

        $wire.on('sendAuthRequest', ({ token }) => {
            socket.send(JSON.stringify({
                'event': 'auth',
                'args': [token]
            }));
        });

        $wire.on('sendServerCommand', ({ command }) => {
            socket.send(JSON.stringify({
                'event': 'send command',
                'args': [command]
            }));
        });
    </script>
    @endscript
</x-filament::widget>
