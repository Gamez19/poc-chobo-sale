<div>
    <header class="page-header">
        <div>
            <p class="eyebrow">Rentabilidad</p>
            <h1>Ganancias del período</h1>
            <p class="page-description">Ventas menos compras del período seleccionado.</p>
        </div>
    </header>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Filtros</h2>
                <p>Las ventas usan la fecha de venta; las compras, la fecha de compra.</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="filters">
                <div class="form-group">
                    <label for="profit-date-from">Desde</label>
                    <input id="profit-date-from" type="date" class="input" wire:model.live="dateFrom">
                </div>
                <div class="form-group">
                    <label for="profit-date-to">Hasta</label>
                    <input id="profit-date-to" type="date" class="input" wire:model.live="dateTo">
                </div>
                <a href="{{ route('profits') }}" wire:navigate class="btn btn-secondary">Limpiar</a>
            </div>
            @error('dateFrom')
                <p class="field-error" role="alert" style="margin-top:10px">{{ $message }}</p>
            @enderror
        </div>
    </section>

    <section class="profit-summary" aria-label="Resumen de ganancias">
        <article class="mini-stat">
            <small>Ventas</small>
            <strong>{{ \App\Support\Money::format($salesCents) }}</strong>
            <span class="table-secondary">Total vendido en el período</span>
        </article>
        <article class="mini-stat">
            <small>Compras</small>
            <strong>{{ \App\Support\Money::format($purchasesCents) }}</strong>
            <span class="table-secondary">Total gastado en el período</span>
        </article>
        <article class="mini-stat">
            <small>Ganancia real</small>
            <strong>{{ \App\Support\Money::format($profitCents) }}</strong>
            <span class="table-secondary">Ventas menos compras</span>
        </article>
        <article class="mini-stat is-highlighted">
            <small>Margen real</small>
            <strong>{{ $marginPercent }} %</strong>
            <span class="table-secondary">Ganancia real sobre las ventas del período</span>
        </article>
    </section>

    <div class="profit-sections">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Ventas por variante</h2>
                    <p>Unidades e ingresos del período</p>
                </div>
                <span class="badge neutral">{{ $salesRows->count() }} resultados</span>
            </div>
            <div class="responsive-table desktop-only">
                <table class="data-table">
                    <thead><tr><th>Variante</th><th class="text-right">Unidades</th><th class="text-right">Ingresos</th></tr></thead>
                    <tbody>
                    @forelse ($salesRows as $row)
                        <tr wire:key="sales-row-{{ $loop->index }}">
                            <td><span class="table-primary">{{ $row['variant_name'] }}</span></td>
                            <td class="text-right amount">{{ number_format($row['units']) }}</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($row['revenue_cents']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state"><strong>No hay ventas para estos filtros</strong><p>Prueba con otro rango.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mobile-card-list mobile-only">
                @forelse ($salesRows as $row)
                    <article class="mobile-data-card" wire:key="sales-card-{{ $loop->index }}">
                        <div class="mobile-data-card-head"><strong>{{ $row['variant_name'] }}</strong><span class="badge success">{{ \App\Support\Money::format($row['revenue_cents']) }}</span></div>
                        <dl class="mobile-data-grid"><div><dt>Unidades</dt><dd>{{ number_format($row['units']) }}</dd></div></dl>
                    </article>
                @empty
                    <div class="empty-state"><strong>No hay ventas para estos filtros</strong><p>Prueba con otro rango.</p></div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Compras del período</h2>
                    <p>Gastos registrados en el rango</p>
                </div>
                <span class="badge neutral">{{ $purchases->count() }} resultados</span>
            </div>
            <div class="responsive-table desktop-only">
                <table class="data-table">
                    <thead><tr><th>Fecha</th><th>Insumos</th><th>Notas</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                    @forelse ($purchases as $purchase)
                        <tr wire:key="purchase-row-{{ $purchase->id }}">
                            <td>{{ $purchase->purchased_at->translatedFormat('d M Y') }}</td>
                            <td>{{ $purchase->rawMaterials->pluck('name')->join(', ') ?: '—' }}</td>
                            <td>{{ $purchase->notes ?: '—' }}</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($purchase->total_cents) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty-state"><strong>No hay compras para estos filtros</strong><p>Prueba con otro rango.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mobile-card-list mobile-only">
                @forelse ($purchases as $purchase)
                    <article class="mobile-data-card" wire:key="purchase-card-{{ $purchase->id }}">
                        <div class="mobile-data-card-head"><strong>{{ $purchase->purchased_at->translatedFormat('d M Y') }}</strong><span class="badge warning">{{ \App\Support\Money::format($purchase->total_cents) }}</span></div>
                        <small>{{ $purchase->rawMaterials->pluck('name')->join(', ') ?: '—' }}</small>
                        @if ($purchase->notes)
                            <small>{{ $purchase->notes }}</small>
                        @endif
                    </article>
                @empty
                    <div class="empty-state"><strong>No hay compras para estos filtros</strong><p>Prueba con otro rango.</p></div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Ingreso potencial</h2>
                    <p>Stock disponible al precio de venta actual: {{ \App\Support\Money::format($potentialRevenueCents) }}</p>
                </div>
                <span class="badge neutral">{{ $potentialRows->count() }} resultados</span>
            </div>
            <div class="responsive-table desktop-only">
                <table class="data-table">
                    <thead><tr><th>Variante</th><th class="text-right">Stock</th><th class="text-right">Ingreso posible</th></tr></thead>
                    <tbody>
                    @forelse ($potentialRows as $row)
                        <tr wire:key="potential-row-{{ $loop->index }}">
                            <td><span class="table-primary">{{ $row['variant_name'] }}</span></td>
                            <td class="text-right amount">{{ number_format($row['units']) }}</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($row['revenue_cents']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="empty-state"><strong>No hay stock disponible</strong><p>Registra producción para ver el ingreso potencial.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mobile-card-list mobile-only">
                @forelse ($potentialRows as $row)
                    <article class="mobile-data-card" wire:key="potential-card-{{ $loop->index }}">
                        <div class="mobile-data-card-head"><strong>{{ $row['variant_name'] }}</strong><span class="badge warning">{{ \App\Support\Money::format($row['revenue_cents']) }}</span></div>
                        <dl class="mobile-data-grid"><div><dt>Stock</dt><dd>{{ number_format($row['units']) }}</dd></div></dl>
                    </article>
                @empty
                    <div class="empty-state"><strong>No hay stock disponible</strong><p>Registra producción para ver el ingreso potencial.</p></div>
                @endforelse
            </div>
        </section>
    </div>
</div>
