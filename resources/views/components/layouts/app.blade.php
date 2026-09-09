<!DOCTYPE html>
<html lang="es" data-theme="{{ auth()->user()?->theme ?? 'claro' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Control de inventario, lotes y ventas">
    <title>{{ $title ?? 'Cacao Control' }} · Cacao Control</title>
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
    @endphp

    <div class="app-shell">
        <aside class="sidebar">
            <a class="brand" href="{{ route('dashboard') }}" wire:navigate aria-label="Ir al resumen">
                <span class="brand-mark">C</span>
                <span>
                    <strong>Cacao Control</strong>
                    <small>Inventario & ventas</small>
                </span>
            </a>

            <nav class="side-nav" aria-label="Navegación principal">
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}"
                       wire:navigate
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
                                    <svg viewBox="0 0 24 24"><circle cx="8" cy="8" r="4"/><path d="M8 4v8M4 8h8"/><path d="M13 9.5a4 4 0 1 1-1 7.8"/><path d="M16 13h4M16 17h3"/></svg>
                                    @break
                                @case('settings')
                                    <svg viewBox="0 0 24 24"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7Z"/><path d="m19.4 15 .1.1a2 2 0 1 1-2.8 2.8l-.1-.1a2 2 0 0 0-3.4 1.4v.2a2 2 0 1 1-4 0v-.2a2 2 0 0 0-3.4-1.4l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A2 2 0 0 0 3.7 12a2 2 0 0 0-.7-1.5l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A2 2 0 0 0 9.2 6.3h.2a2 2 0 0 0 1.6-2v-.2a2 2 0 1 1 4 0v.2a2 2 0 0 0 1.6 2h.2a2 2 0 0 0 1.4-.6l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a2 2 0 0 0-1.4 3.4v.2a2 2 0 0 0 2 1.6h.2a2 2 0 1 1 0 4h-.2a2 2 0 0 0-2 1.6Z"/></svg>
                            @endswitch
                        </span>
                        {{ $item['label'] }}
                    </a>
                @endforeach
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
                    <a class="user-context" href="{{ route('settings') }}" wire:navigate>
                        <span class="user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="user-context-name">{{ auth()->user()->name }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-soft btn-sm" type="submit">Salir</button>
                    </form>
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

