<div>
    <header class="page-header">
        <div>
            <p class="eyebrow">Análisis comercial</p>
            <h1>Reporte de ventas</h1>
            <p class="page-description">Consulta ingresos y unidades por rango de fechas, lote y categoría de producto.</p>
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
                    <label for="date-from">Desde</label>
                    <input id="date-from" type="date" class="input" wire:model.live="dateFrom">
                </div>
                <div class="form-group">
                    <label for="date-to">Hasta</label>
                    <input id="date-to" type="date" class="input" wire:model.live="dateTo">
                </div>
                <div class="form-group">
                    <label for="lot-filter">Lote</label>
                    <select id="lot-filter" class="select" wire:model.live="lotId">
                        <option value="">Todos los lotes</option>
                        @foreach ($lots as $lot)
                            <option value="{{ $lot->id }}">{{ $lot->code }} · {{ $lot->produced_at->format('d/m/Y') }}</option>
                        @endforeach
                    </select>
                </div>
                <a href="{{ route('reports') }}" wire:navigate class="btn btn-secondary">Limpiar</a>
            </div>
            @error('dateFrom') <p class="field-error" role="alert" style="margin-top:10px">{{ $message }}</p> @enderror
        </div>
    </section>

    <section class="report-summary" aria-label="Resumen del reporte">
        <article class="mini-stat">
            <small>Ingresos en el período</small>
            <strong>{{ \App\Support\Money::format($totalRevenueCents) }}</strong>
        </article>
        <article class="mini-stat">
            <small>Unidades vendidas</small>
            <strong>{{ number_format($totalUnits) }}</strong>
        </article>
        <article class="mini-stat">
            <small>Ventas registradas</small>
            <strong>{{ number_format($salesCount) }}</strong>
        </article>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Ventas por lote y categoría</h2>
                <p>{{ $dateFrom }} al {{ $dateTo }}</p>
            </div>
            <span class="badge neutral">{{ $rows->count() }} resultados</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Lote</th><th>Producto</th><th>Producción</th><th class="text-right">Unidades</th><th class="text-right">Ingresos</th></tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <span class="table-primary">{{ $row['lot']->code }}</span>
                            <span class="table-secondary">{{ $row['lot']->deleted_at ? 'Eliminado' : ($row['lot']->status === 'open' ? 'Activo' : 'Agotado') }}</span>
                        </td>
                        <td>
                            <span class="table-primary">{{ $row['variant_name'] }}</span>
                            <span class="table-secondary">{{ $row['variant']->product->name }}</span>
                        </td>
                        <td>{{ $row['lot']->produced_at->translatedFormat('d M Y') }}</td>
                        <td class="text-right amount">{{ number_format($row['units']) }}</td>
                        <td class="text-right amount">{{ \App\Support\Money::format($row['revenue_cents']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
                                <strong>No hay ventas para estos filtros</strong>
                                <p>Prueba con otro rango de fechas o selecciona todos los lotes.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="3" class="table-primary">Total</td>
                            <td class="text-right amount">{{ number_format($totalUnits) }}</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($totalRevenueCents) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
</div>
