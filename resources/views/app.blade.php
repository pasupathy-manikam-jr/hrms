<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- Brand title from Settings → Brand, read by the tab-title callback in app.tsx --}}
        <meta name="application-name" content="{{ config('app.name', 'HRM') }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(0.984 0.004 270);
            }

            html.dark {
                background-color: oklch(0.17 0.02 275);
            }
        </style>

        {{-- ?v= changes whenever an icon file changes, so browsers drop their cached favicon --}}
        <link rel="icon" href="/favicon.ico?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
        <link rel="icon" href="/favicon.svg?v={{ filemtime(public_path('favicon.svg')) }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ filemtime(public_path('apple-touch-icon.png')) }}">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])

        {{-- Brand colour from Settings → Brand (after the app stylesheet so it wins) --}}
        <style>
            :root, .dark {
                --primary: {{ \App\Models\Setting::primaryColor() }};
                --ring: {{ \App\Models\Setting::primaryColor() }};
            }
        </style>
        <x-inertia::head>
            <title>{{ config('app.name', 'HRM') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
