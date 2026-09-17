{{-- Standalone, like the error pages: this is not a panel route, so no panel render hook
     fires here. Both stylesheets are named explicitly, in the panel's own order, or every
     class below is undefined. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="fi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ trans('wyvern.api_docs.title') }} - {{ config('app.name') }}</title>
    <link rel="icon" href="{{ config('app.favicon') }}">

    @vite(['resources/css/app.css', 'resources/css/wyvern-theme.css'])
    {{ filament()->getFontHtml() }}

    <script>
        // The panel's own pre-paint theme guard, repeated because this page is outside it.
        const theme = localStorage.getItem('theme') ?? @js(filament()->getDefaultThemeMode()->value);

        if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="fi-body wy-docs-page">
    <main class="wy-docs">
        <h1 class="wy-docs-title">{{ trans('wyvern.api_docs.title') }}</h1>
        <p class="wy-docs-lead">{{ trans('wyvern.api_docs.lead') }}</p>

        <a href="/docs/api/application" class="wy-docs-link">
            <x-filament::icon icon="tabler-server-cog" />
            <span>
                <strong>{{ trans('wyvern.api_docs.application') }}</strong>
                {{ trans('wyvern.api_docs.application_hint') }}
            </span>
        </a>

        <a href="/docs/api/client" class="wy-docs-link">
            <x-filament::icon icon="tabler-user-cog" />
            <span>
                <strong>{{ trans('wyvern.api_docs.client') }}</strong>
                {{ trans('wyvern.api_docs.client_hint') }}
            </span>
        </a>

        <p class="wy-docs-note">
            <x-filament::icon icon="tabler-info-circle" />
            {{ trans('wyvern.api_docs.note') }}
        </p>
    </main>
</body>
</html>
