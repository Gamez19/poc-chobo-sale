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
            ['route' => 'reports', 'label' => 'Reportes', 'icon' => 'chart'],
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

    <div class="app-shell" x-data="{ sidebarExpanded: false }" :class="{ 'is-sidebar-expanded': sidebarExpanded }">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a class="brand" href="{{ route('dashboard') }}" wire:navigate aria-label="Ir al resumen">
                    <span class="brand-mark">C</span>
                    <span class="brand-copy">
                        <strong>Choco Aventuras</strong>
                        <small>Inventario & ventas</small>
                    </span>
                </a>

                <button class="sidebar-toggle" type="button" aria-expanded="false" aria-label="Expandir menú de navegación" @click="sidebarExpanded = ! sidebarExpanded" :aria-expanded="sidebarExpanded.toString()" :aria-label="sidebarExpanded ? 'Contraer menú de navegación' : 'Expandir menú de navegación'">
                    <span class="sidebar-toggle-menu" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </span>
                    <span class="sidebar-toggle-close" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg>
                    </span>
                </button>
            </div>

            <nav class="side-nav" aria-label="Navegación principal">
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}"
                       wire:navigate
                       aria-label="{{ $item['label'] }}"
                       @class(['nav-link', 'is-active' => request()->routeIs($item['route'])])>
                        <span class="nav-icon" aria-hidden="true">
                            @switch($item['icon'])
                                @case('grid')
                                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>
                                    @break
                                @case('leaf')
                                    <svg viewBox="0 0 24 24"><path d="M20 4c-8 0-14 4-14 10 0 3 2 5 5 5 6 0 9-7 9-15Z"/><path d="M4 21c2-6 6-9 12-12"/></svg>
                                    @break
                                @case('box')
                                    <svg viewBox="0 0 24 24"><path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 7 8 4 8-4v10l-8 4-8-4V7Z"/><path d="M12 11v10"/></svg>
                                    @break
                                @case('layers')
                                    <svg viewBox="0 0 24 24"><path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/></svg>
                                    @break
                                @case('cart')
                                    <svg viewBox="0 0 24 24"><path d="M3 3h2l2.4 11.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                                    @break
                                @case('chart')
                                    <svg viewBox="0 0 24 24"><path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/></svg>
                                    @break
                                @case('coins')
                                    <x-heroicon-o-banknotes />
                                    @break
                                @case('settings')
                                    <x-heroicon-o-cog-6-tooth />
                            @endswitch
                        </span>
                        <span class="nav-link-label">{{ $item['label'] }}</span>
                    </a>
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
                <div>
                    <span class="topbar-kicker">OPERACIONES</span>
                    <strong>{{ $title ?? 'Resumen' }}</strong>
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

