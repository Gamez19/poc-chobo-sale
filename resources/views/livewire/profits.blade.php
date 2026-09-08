<div>
    <header class="page-header">
        <div>
            <p class="eyebrow">Rentabilidad</p>
            <h1>Ganancias reales y proyectadas</h1>
            <p class="page-description">Compara lo ganado en ventas con el margen que todavía puede generar el inventario disponible.</p>
        </div>
    </header>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Filtros</h2>
                <p>La ganancia real usa la fecha de venta; la proyectada, la fecha de producción.</p>
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
                <div class="form-group">
                    <label for="profit-lot-filter">Lote</label>
                    <select id="profit-lot-filter" class="select" wire:model.live="lotId">
                        <option value="">Todos los lotes</option>
                        @foreach ($lots as $lot)
                            <option value="{{ $lot->id }}">{{ $lot->code }} · {{ $lot->produced_at->format('d/m/Y') }}</option>
                        @endforeach
                    </select>
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
            <small>Ganancia real</small>
            <strong>{{ \App\Support\Money::format($realizedProfitCents) }}</strong>
            <span class="table-secondary">Ingresos menos costo de lo vendido</span>
        </article>
        <article class="mini-stat">
            <small>Ganancia proyectada</small>
            <strong>{{ \App\Support\Money::format($projectedProfitCents) }}</strong>
            <span class="table-secondary">Margen del stock disponible</span>
        </article>
        <article class="mini-stat is-highlighted">
            <small>Ganancia combinada</small>
            <strong>{{ \App\Support\Money::format($realizedProfitCents + $projectedProfitCents) }}</strong>
            <span class="table-secondary">Real más proyección del período</span>
        </article>
    </section>

    <div class="profit-sections">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Ganancia real por lote</h2>
                    <p>Ventas asignadas al costo real de producción</p>
                </div>
                <span class="badge neutral">{{ $realizedRows->count() }} resultados</span>
            </div>
            <div class="responsive-table desktop-only">
                <table class="data-table">
                    <thead><tr><th>Lote</th><th>Variante</th><th class="text-right">Unidades</th><th class="text-right">Ingresos</th><th class="text-right">Costo</th><th class="text-right">Ganancia</th></tr></thead>
                    <tbody>
                    @forelse ($realizedRows as $row)
                        <tr>
                            <td><span class="table-primary">{{ $row['lot']->code }}</span><span class="table-secondary">{{ $row['lot']->produced_at->translatedFormat('d M Y') }}</span></td>
                            <td><span class="table-primary">{{ $row['variant_name'] }}</span><span class="table-secondary">{{ $row['variant']->product->name }}</span></td>
                            <td class="text-right amount">{{ number_format($row['units']) }}</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($row['revenue_cents']) }}</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($row['cost_cents']) }}</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($row['profit_cents']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><strong>No hay ventas para estos filtros</strong><p>Prueba con otro rango o lote.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mobile-card-list mobile-only">
                @forelse ($realizedRows as $row)
                    <article class="mobile-data-card">
                        <div class="mobile-data-card-head"><strong>{{ $row['variant_name'] }}</strong><span class="badge success">{{ \App\Support\Money::format($row['profit_cents']) }}</span></div>
                        <small>{{ $row['lot']->code }} · {{ $row['lot']->produced_at->translatedFormat('d M Y') }}</small>
                        <dl class="mobile-data-grid"><div><dt>Unidades</dt><dd>{{ number_format($row['units']) }}</dd></div><div><dt>Ingresos</dt><dd>{{ \App\Support\Money::format($row['revenue_cents']) }}</dd></div><div><dt>Costo</dt><dd>{{ \App\Support\Money::format($row['cost_cents']) }}</dd></div></dl>
                    </article>
                @empty
                    <div class="empty-state"><strong>No hay ventas para estos filtros</strong><p>Prueba con otro rango o lote.</p></div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Ganancia proyectada</h2>
                    <p>Stock disponible al precio de venta actual</p>
                </div>
                <span class="badge neutral">{{ $projectedRows->count() }} resultados</span>
            </div>
            <div class="responsive-table desktop-only">
                <table class="data-table">
                    <thead><tr><th>Lote</th><th>Variante</th><th class="text-right">Stock</th><th class="text-right">Ingreso posible</th><th class="text-right">Costo</th><th class="text-right">Ganancia</th></tr></thead>
                    <tbody>
                    @forelse ($projectedRows as $row)
                        <tr>
                            <td><span class="table-primary">{{ $row['lot']->code }}</span><span class="table-secondary">{{ $row['lot']->produced_at->translatedFormat('d M Y') }}</span></td>
                            <td><span class="table-primary">{{ $row['variant_name'] }}</span><span class="table-secondary">{{ $row['variant']->product->name }}</span></td>
                            <td class="text-right amount">{{ number_format($row['units']) }}</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($row['revenue_cents']) }}</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($row['cost_cents']) }}</td>
                            <td class="text-right amount">{{ \App\Support\Money::format($row['profit_cents']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><strong>No hay stock proyectable</strong><p>Elige otro rango o lote.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mobile-card-list mobile-only">
                @forelse ($projectedRows as $row)
                    <article class="mobile-data-card">
                        <div class="mobile-data-card-head"><strong>{{ $row['variant_name'] }}</strong><span class="badge warning">{{ \App\Support\Money::format($row['profit_cents']) }}</span></div>
                        <small>{{ $row['lot']->code }} · {{ $row['lot']->produced_at->translatedFormat('d M Y') }}</small>
                        <dl class="mobile-data-grid"><div><dt>Stock</dt><dd>{{ number_format($row['units']) }}</dd></div><div><dt>Ingreso posible</dt><dd>{{ \App\Support\Money::format($row['revenue_cents']) }}</dd></div><div><dt>Costo</dt><dd>{{ \App\Support\Money::format($row['cost_cents']) }}</dd></div></dl>
                    </article>
                @empty
                    <div class="empty-state"><strong>No hay stock proyectable</strong><p>Elige otro rango o lote.</p></div>
                @endforelse
            </div>
        </section>
    </div>
</div>
