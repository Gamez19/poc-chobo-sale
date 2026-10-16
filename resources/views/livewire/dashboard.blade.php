<div class="dashboard-content">
    <header class="page-header">
        <div>
            <p class="eyebrow">Pulso del negocio</p>
            <h1>Todo bajo control.</h1>
            <p class="page-description">Inventario, producción y ventas de hoy en una sola vista.</p>
        </div>
        <a href="{{ route('sales') }}" class="btn btn-primary" wire:navigate>
            <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Registrar venta
        </a>
    </header>

    <section class="stats-grid" aria-label="Indicadores principales">
        <article class="stat-card is-featured">
            <div class="stat-label">
                Inventario terminado
                <span class="stat-icon"><svg viewBox="0 0 24 24"><path d="m4 7 8-4 8 4-8 4-8-4Z"/><path d="m4 7 8 4 8-4v10l-8 4-8-4V7Z"/></svg></span>
            </div>
            <strong class="stat-value">{{ number_format($finishedStock) }}</strong>
            <span class="stat-meta">unidades disponibles</span>
        </article>

        <article class="stat-card">
            <div class="stat-label">
                Ventas de hoy
                <span class="stat-icon"><svg viewBox="0 0 24 24"><path d="M4 17 10 11l4 4 6-8"/><path d="M14 7h6v6"/></svg></span>
            </div>
            <strong class="stat-value">{{ \App\Support\Money::format($salesTodayCents) }}</strong>
            <span class="stat-meta">{{ number_format($unitsToday) }} unidades vendidas</span>
        </article>

        <article class="stat-card">
            <div class="stat-label">
                Variantes activas
                <span class="stat-icon"><svg viewBox="0 0 24 24"><path d="M5 7h14M5 12h14M5 17h9"/></svg></span>
            </div>
            <strong class="stat-value">{{ $variants->count() }}</strong>
            <span class="stat-meta">precios independientes</span>
        </article>

        <article class="stat-card">
            <div class="stat-label">
                Compras del mes
                <span class="stat-icon"><svg viewBox="0 0 24 24"><path d="M6 6h15l-1.5 9h-12L6 6Zm0 0L5 3H2"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></span>
            </div>
            <strong class="stat-value">{{ \App\Support\Money::format($monthPurchasesCents) }}</strong>
            <span class="stat-meta">gastado en insumos</span>
        </article>
    </section>

    <div class="split-grid" style="margin-top:18px">
        <section class="panel dashboard-stock-panel">
            <div class="panel-header">
                <div>
                    <h2>Existencia por variante</h2>
                    <p>Productos listos para vender</p>
                </div>
                <a href="{{ route('production-lots') }}" class="btn btn-secondary btn-sm" wire:navigate>Nuevo lote</a>
            </div>
            <div class="panel-body stock-list">
                @forelse ($variants as $variant)
                    <div class="stock-row">
                        <div class="stock-name">
                            <span class="avatar">{{ str($variant->name)->substr(0, 2)->upper() }}</span>
                            <span>
                                <strong>{{ $variant->name }}</strong>
                                <small>{{ \App\Support\Money::format($variant->price_cents) }}</small>
                            </span>
                        </div>
                        <span class="stock-count">
                            {{ number_format($variant->available_stock ?? 0) }}
                            <small>disponibles</small>
                        </span>
                    </div>
                @empty
                    <div class="empty-state">
                        <strong>Aún no hay productos</strong>
                        <p>Crea tu primer producto y configura sus variantes.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="panel dashboard-attention-panel">
            <div class="panel-header">
                <div>
                    <h2>Últimas compras</h2>
                    <p>Gastos recientes en insumos</p>
                </div>
                <a href="{{ route('raw-materials') }}" class="btn btn-secondary btn-sm" wire:navigate>Ver compras</a>
            </div>
            <div class="panel-body stock-list">
                @forelse ($recentPurchases as $purchase)
                    <div class="stock-row" wire:key="purchase-{{ $purchase->id }}">
                        <div class="stock-name">
                            <span>
                                <strong>{{ $purchase->rawMaterials->pluck('name')->join(', ') ?: 'Sin insumos' }}</strong>
                                <small>{{ $purchase->purchased_at->translatedFormat('d M Y') }}</small>
                            </span>
                        </div>
                        <span class="stock-count">{{ \App\Support\Money::format($purchase->total_cents) }}</span>
                    </div>
                @empty
                    <div class="empty-state">
                        <strong>Sin compras todavía</strong>
                        <p>Registra tus compras en Materias primas.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="panel-grid" style="margin-top:18px">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Últimas ventas</h2>
                    <p>Actividad comercial reciente</p>
                </div>
                <a href="{{ route('reports') }}" class="btn btn-secondary btn-sm" wire:navigate>Ver reporte</a>
            </div>
            <div class="table-wrap desktop-only">
                <table class="data-table">
                    <thead><tr><th>Venta</th><th>Unidades</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                    @forelse ($recentSales as $sale)
                        <tr>
                            <td>
                                <span class="table-primary">{{ $sale->number }}</span>
                                <span class="table-secondary">{{ $sale->sold_at->translatedFormat('d M · H:i') }}</span>
                            </td>
                            <td>{{ $sale->items->sum('quantity') }}</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($sale->total_cents) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state"><strong>Sin ventas todavía</strong><p>La actividad aparecerá aquí.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mobile-card-list mobile-only">
                @forelse ($recentSales as $sale)
                    <article class="mobile-data-card">
                        <div class="mobile-data-card-head"><strong>{{ $sale->number }}</strong><span class="amount">{{ \App\Support\Money::format($sale->total_cents) }}</span></div>
                        <small>{{ $sale->sold_at->translatedFormat('d M · H:i') }}</small>
                        <span class="badge neutral">{{ $sale->items->sum('quantity') }} unidades</span>
                    </article>
                @empty
                    <div class="empty-state"><strong>Sin ventas todavía</strong><p>La actividad aparecerá aquí.</p></div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Lotes recientes</h2>
                    <p>Producción agregada al inventario</p>
                </div>
            </div>
            <div class="table-wrap desktop-only">
                <table class="data-table">
                    <thead><tr><th>Lote</th><th>Producido</th><th class="text-right">Disponible</th></tr></thead>
                    <tbody>
                    @forelse ($recentLots as $lot)
                        <tr>
                            <td>
                                <span class="table-primary">{{ $lot->code }}</span>
                                <span class="table-secondary">{{ $lot->items->count() }} variantes</span>
                            </td>
                            <td>{{ $lot->produced_at->translatedFormat('d M Y') }}</td>
                            <td class="text-right amount">{{ $lot->items->sum('quantity_available') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state"><strong>Sin lotes todavía</strong><p>La producción aparecerá aquí.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mobile-card-list mobile-only">
                @forelse ($recentLots as $lot)
                    <article class="mobile-data-card">
                        <div class="mobile-data-card-head"><strong>{{ $lot->code }}</strong><span class="amount">{{ $lot->items->sum('quantity_available') }} disponibles</span></div>
                        <small>{{ $lot->produced_at->translatedFormat('d M Y') }} · {{ $lot->items->count() }} variantes</small>
                    </article>
                @empty
                    <div class="empty-state"><strong>Sin lotes todavía</strong><p>La producción aparecerá aquí.</p></div>
                @endforelse
            </div>
        </section>
    </div>
</div>
