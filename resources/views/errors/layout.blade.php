<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    @class([
        'fi min-h-screen',
        'dark' => filament()->hasDarkModeForced(),
    ])>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link rel="icon" href="{{ config('app.favicon') }}" />

    <title>
        {{ $title }} - {{ config('app.name') }}
    </title>

    @filamentStyles

    {{-- An error page is not a panel page, so none of the panel render hooks fire here:
         app.css goes on STYLES_BEFORE and wyvern-theme.css on STYLES_AFTER, and neither
         ran. filament()->getTheme() has no current panel to resolve either, so it
         emitted nothing. The result was a page whose only stylesheet was the font one —
         every Tailwind class in the markup below, including its own dark: variants, was
         undefined, which is why a 404 rendered as a white slab on a dark ground.

         Loaded in the panel's own order: Tailwind and Filament first, ours last. --}}
    @vite(['resources/css/app.css', 'resources/css/wyvern-theme.css'])

    {{ filament()->getTheme()->getHtml() }}
    {{ filament()->getFontHtml() }}

    <style>
        :root {
            --font-family: '{!! filament()->getFontFamily() !!}';
        }
    </style>

    @if (! filament()->hasDarkMode())
        <script>
            localStorage.setItem('theme', 'light');
        </script>
    @elseif (filament()->hasDarkModeForced())
        <script>
            localStorage.setItem('theme', 'dark');
        </script>
    @else
        <script>
            const theme = localStorage.getItem('theme') ??
            @js(filament()->getDefaultThemeMode()->value)

            if (
                theme === 'dark' ||
                (theme === 'system' &&
                    window.matchMedia('(prefers-color-scheme: dark)')
                        .matches)
            ) {
                document.documentElement.classList.add('dark');
            }
        </script>
    @endif
</head>

<body class="fi-body min-h-screen bg-gray-50 font-normal text-gray-950 antialiased dark:bg-gray-950 dark:text-white">
<div class="fi-simple-layout flex min-h-screen flex-col items-center">
    <div class="fi-simple-main-ctn flex w-full flex-grow items-center justify-center">
        <main
            class="fi-simple-main my-16 w-full bg-white px-6 py-12 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:rounded-xl sm:px-12 sm:max-w-lg">
            <div class="fi-simple-page">
                <section class="grid auto-cols-fr gap-y-6">
                    <header class="fi-simple-header flex flex-col items-center">
                        <h1 class="fi-simple-header-heading flex text-center text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                            @if(filled($icon))
                                <x-filament::icon icon="{{ $icon }}" class="h-8 w-8" />
                            @endif

                            {{$code}} | {{ $title }}
                        </h1>

                        <p class="fi-simple-header-subheading mt-2 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ $subtitle instanceof \Closure ? $subtitle() : $subtitle }}
                        </p>
                    </header>
                </section>
            </div>
        </main>
    </div>
</div>
</body>

</html>
