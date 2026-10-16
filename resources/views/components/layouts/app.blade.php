<!DOCTYPE html>
<html lang="es" data-theme="{{ auth()->user()?->theme ?? 'claro' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Control de inventario, lotes y ventas">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>{{ $title ?? 'Choco Aventuras' }} · Choco Aventuras</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    @php
        $navigation = [
            ['route' => 'dashboard', 'label' => 'Resumen', 'icon' => 'grid'],
            ['route' => 'raw-materials', 'label' => 'Materias primas', 'icon' => 'leaf'],
            ['route' => 'products', 'label' => 'Productos', 'icon' => 'box'],
            ['route' => 'production-lots', 'label' => 'Lotes', 'icon' => 'layers'],
            ['route' => 'sales', 'label' => 'Ventas', 'icon' => 'cart'],
            ['label' => 'Reportes', 'icon' => 'chart', 'children' => [
                ['route' => 'reports', 'label' => 'Ventas'],
                ['route' => 'reports.purchases', 'label' => 'Compras'],
            ]],
            ['route' => 'profits', 'label' => 'Ganancias', 'icon' => 'coins'],
            ['route' => 'settings', 'label' => 'Configuración', 'icon' => 'settings'],
        ];
        $currentHour = now()->hour;
        $greeting = match (true) {
            $currentHour >= 5 && $currentHour < 12 => 'Buenos días',
            $currentHour >= 12 && $currentHour < 16 => 'Buenas tardes',
            default => 'Buenas noches',
        };
    @endphp

    <div class="app-shell" x-data="{ menuOpen: false }" :class="{ 'is-menu-open': menuOpen }" @keydown.escape.window="menuOpen = false">
        <div class="sidebar-backdrop" x-cloak x-show="menuOpen" x-transition.opacity @click="menuOpen = false" aria-hidden="true"></div>
        <aside class="sidebar" id="navigation-drawer" :aria-hidden="(! menuOpen).toString()">
            <div class="sidebar-header">
                <a class="brand" href="{{ route('dashboard') }}" wire:navigate aria-label="Ir al resumen" @click="menuOpen = false">
                    <span class="brand-mark">C</span>
                </a>

                <button class="sidebar-close" type="button" aria-label="Cerrar menú de navegación" @click="menuOpen = false">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <nav class="side-nav" aria-label="Navegación principal">
                @foreach ($navigation as $item)
                    @if (isset($item['children']))
                        @php
                            $isGroupActive = collect($item['children'])->contains(fn (array $child): bool => request()->routeIs($child['route']));
                        @endphp
                        <div class="nav-group" x-data="{ open: @js($isGroupActive) }">
                            <button type="button"
                                    @click="open = ! open"
                                    :aria-expanded="open.toString()"
                                    aria-controls="nav-group-{{ $loop->index }}"
                                    @class(['nav-link', 'nav-group-toggle', 'is-group-active' => $isGroupActive])>
                                <span class="nav-icon" aria-hidden="true"><x-nav-icon :name="$item['icon']" /></span>
                                <span class="nav-link-label">{{ $item['label'] }}</span>
                                <svg class="nav-group-chevron" :class="{ 'is-open': open }" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="nav-sublist" id="nav-group-{{ $loop->index }}" x-show="open" x-cloak>
                                @foreach ($item['children'] as $child)
                                    <a href="{{ route($child['route']) }}"
                                       wire:navigate
                                       aria-label="{{ $item['label'] }} de {{ mb_strtolower($child['label']) }}"
                                       @click="menuOpen = false"
                                       @class(['nav-link', 'nav-sublink', 'is-active' => request()->routeIs($child['route'])])>
                                        <span class="nav-link-label">{{ $child['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ route($item['route']) }}"
                           wire:navigate
                           aria-label="{{ $item['label'] }}"
                           @click="menuOpen = false"
                           @class(['nav-link', 'is-active' => request()->routeIs($item['route'])])>
                            <span class="nav-icon" aria-hidden="true"><x-nav-icon :name="$item['icon']" /></span>
                            <span class="nav-link-label">{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach

                <form class="sidebar-logout" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="nav-link" type="submit" aria-label="Salir">
                        <span class="nav-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 3v18H10"/></svg>
                        </span>
                        <span class="nav-link-label">Salir</span>
                    </button>
                </form>
            </nav>

            <div class="sidebar-note">
                <span class="status-dot"></span>
                <div>
                    <strong>Sistema activo</strong>
                    <small>Base de datos local SQLite</small>
                </div>
            </div>
        </aside>

        <div class="main-area">
            <header class="topbar">
                <div class="topbar-leading">
                    <button class="menu-toggle" type="button" aria-label="Abrir menú de navegación" aria-controls="navigation-drawer" :aria-expanded="menuOpen.toString()" @click="menuOpen = true">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                    <div class="topbar-heading">
                        <span class="topbar-kicker">OPERACIONES</span>
                        <strong>{{ $title ?? 'Resumen' }}</strong>
                    </div>
                </div>
                <div class="topbar-actions">
                    <p class="user-greeting">{{ $greeting }}, {{ auth()->user()?->name }}</p>
                    <div class="today-chip">
                        <span>{{ now()->translatedFormat('D') }}</span>
                        {{ now()->translatedFormat('d M Y') }}
                    </div>
                </div>
            </header>

            <main class="content">
                @if (session('success'))
                    <div class="toast-success" role="status">
                        <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
                        {{ session('success') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>

