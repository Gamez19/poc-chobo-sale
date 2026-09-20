<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="cacao">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#3b2117">

        <title>{{ config('app.name', 'Choco Aventuras') }} · Acceso</title>
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="login-page">
        <main class="login-shell">
            <section class="login-visual" aria-labelledby="login-brand-title">
                <a class="login-brand" href="{{ route('login') }}" wire:navigate>
                    <span class="login-brand-mark">
                        <img src="{{ asset('images/chocobanano.png') }}" alt="" width="54" height="54">
                    </span>
                    <span>
                        <strong>Choco Aventuras</strong>
                        <small>Inventario & ventas</small>
                    </span>
                </a>

                <div class="login-visual-copy">
                    <p class="login-eyebrow">Control de inventario</p>
                    <h1 id="login-brand-title">Todo el sabor,<br><em>todo bajo control.</em></h1>
                    <p>Organizá tus materias primas, recetas y ventas desde un solo lugar.</p>
                </div>

                <div class="login-highlight" aria-label="Funciones de Choco Aventuras">
                    <span class="login-highlight-icon">✦</span>
                    <span>
                        <strong>Hecho para tu operación</strong>
                        <small>Stock, costos y producción en orden.</small>
                    </span>
                </div>
            </section>

            <section class="login-panel" aria-label="Acceso a Choco Aventuras">
                <div class="login-form-wrap">
                    <div class="login-mobile-brand">
                        <img src="{{ asset('images/chocobanano.png') }}" alt="Chocobanano" width="64" height="64">
                        <span>Choco Aventuras</span>
                    </div>

                    {{ $slot }}

                    <p class="login-footer">Acceso privado para el equipo de Choco Aventuras</p>
                </div>
            </section>
        </main>

        @livewireScripts
    </body>
</html>
