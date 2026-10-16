<div>
    <header class="page-header">
        <div>
            <p class="eyebrow">Análisis de gastos</p>
            <h1>Reporte de compras</h1>
            <p class="page-description">Consulta cuánto gastaste en compras por rango de fechas e insumo.</p>
        </div>
    </header>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Filtros</h2>
                <p>Los resultados se actualizan automáticamente</p>
            </div>
        </div>
        <div class="panel-body">
            <div class="filters">
                <div class="form-group">
                    <label for="purchase-date-from">Desde</label>
                    <input id="purchase-date-from" type="date" class="input" wire:model.live="dateFrom">
                </div>
                <div class="form-group">
                    <label for="purchase-date-to">Hasta</label>
                    <input id="purchase-date-to" type="date" class="input" wire:model.live="dateTo">
                </div>
                <div class="form-group">
                    <label for="purchase-material-filter">Insumo</label>
                    <select id="purchase-material-filter" class="select" wire:model.live="rawMaterialId">
                        <option value="">Todos los insumos</option>
                        @foreach ($rawMaterials as $rawMaterial)
                            <option value="{{ $rawMaterial->id }}">{{ $rawMaterial->name }}</option>
                        @endforeach
                    </select>
                </div>
                <a href="{{ route('reports.purchases') }}" wire:navigate class="btn btn-secondary">Limpiar</a>
            </div>
            @error('dateFrom') <p class="field-error" role="alert" style="margin-top:10px">{{ $message }}</p> @enderror
        </div>
    </section>

    <section class="report-summary is-inline" aria-label="Resumen del reporte">
        <article class="mini-stat">
            <small>Gastado en el período</small>
            <strong>{{ \App\Support\Money::format($totalSpentCents) }}</strong>
        </article>
        <article class="mini-stat">
            <small>Promedio por compra</small>
            <strong>{{ \App\Support\Money::format($averageCents) }}</strong>
        </article>
        <article class="mini-stat">
            <small>Compras registradas</small>
            <strong>{{ number_format($purchasesCount) }}</strong>
        </article>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Compras del período</h2>
                <p>{{ $dateFrom }} al {{ $dateTo }}</p>
            </div>
            <span class="badge neutral">{{ $purchasesCount }} {{ $purchasesCount === 1 ? 'resultado' : 'resultados' }}</span>
        </div>
        <div class="table-wrap desktop-only">
            <table class="data-table">
                <thead>
                    <tr><th>Fecha</th><th>Insumos</th><th>Notas</th><th class="text-right">Total</th></tr>
                </thead>
                <tbody>
                @forelse ($purchases as $purchase)
                    <tr wire:key="report-purchase-{{ $purchase->id }}">
                        <td>{{ $purchase->purchased_at->translatedFormat('d M Y') }}</td>
                        <td>{{ $purchase->rawMaterials->isEmpty() ? 'Sin insumos marcados' : $purchase->rawMaterials->pluck('name')->join(', ') }}</td>
                        <td>{{ $purchase->notes ?: '—' }}</td>
                        <td class="text-right amount">{{ \App\Support\Money::format($purchase->total_cents) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
                                <strong>No hay compras para estos filtros</strong>
                                <p>Prueba con otro rango de fechas o selecciona todos los insumos.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if ($purchases->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="3" class="table-primary">Total</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($totalSpentCents) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="mobile-card-list mobile-only">
            @forelse ($purchases as $purchase)
                <article class="mobile-data-card" wire:key="report-purchase-card-{{ $purchase->id }}">
                    <div class="mobile-data-card-head">
                        <strong>{{ $purchase->purchased_at->translatedFormat('d M Y') }}</strong>
                        <span class="amount">{{ \App\Support\Money::format($purchase->total_cents) }}</span>
                    </div>
                    <small>{{ $purchase->rawMaterials->isEmpty() ? 'Sin insumos marcados' : $purchase->rawMaterials->pluck('name')->join(', ') }}</small>
                    @if ($purchase->notes)
                        <small>{{ $purchase->notes }}</small>
                    @endif
                </article>
            @empty
                <div class="empty-state">
                    <strong>No hay compras para estos filtros</strong>
                    <p>Prueba con otro rango de fechas o selecciona todos los insumos.</p>
                </div>
            @endforelse
            @if ($purchases->isNotEmpty())
                <article class="mobile-data-card">
                    <div class="mobile-data-card-head">
                        <strong>Total</strong>
                        <span class="amount">{{ \App\Support\Money::format($totalSpentCents) }}</span>
                    </div>
                    <small>{{ $purchasesCount }} {{ $purchasesCount === 1 ? 'compra' : 'compras' }}</small>
                </article>
            @endif
        </div>
    </section>
</div>
