<div>
    <header class="page-header">
        <div>
            <p class="eyebrow">Punto de venta</p>
            <h1>Registrar venta</h1>
            <p class="page-description">Selecciona las cantidades. El sistema descuenta primero los lotes más antiguos disponibles.</p>
        </div>
    </header>

    <div class="split-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Productos</h2>
                    <p>Precios y existencias actuales</p>
                </div>
            </div>
            <div class="panel-body" style="padding-bottom:0">
                <div class="search-box">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                    <input type="search" class="input" wire:model.live.debounce.300ms="search" placeholder="Buscar producto o variante…" aria-label="Buscar producto o variante">
                </div>
            </div>
            <form class="panel-body" wire:submit="recordSale">
                @if ($lastSaleNumber !== '')
                    <div class="toast-success" role="status" style="margin-bottom:16px">
                        Venta {{ $lastSaleNumber }} registrada correctamente.
                    </div>
                @endif

                @if ($variants->isEmpty())
                    <div class="empty-state">
                        <strong>Todavía no hay existencias para vender</strong>
                        <p>
                            Para vender necesitas configurar la receta de la variante en
                            <a href="{{ route('products') }}" wire:navigate>Productos</a>
                            y luego registrar un lote de producción en
                            <a href="{{ route('production-lots') }}" wire:navigate>Lotes de producción</a>.
                            Las cantidades se habilitan cuando el lote tenga unidades disponibles.
                        </p>
                    </div>
                @elseif ($visibleVariants->isEmpty())
                    <div class="empty-state">
                        <strong>No encontramos productos con ese nombre</strong>
                        <p>Revisá la búsqueda o limpiala para ver todo el stock disponible.</p>
                    </div>
                @endif

                <div class="sale-grid">
                    @foreach ($visibleVariants as $variant)
                        @php
                            $quantity = (int) ($quantities[$variant->id] ?? 0);
                            $available = (int) ($variant->available_stock ?? 0);
                        @endphp
                        <div class="sale-option" wire:key="sale-variant-{{ $variant->id }}">
                            <div class="sale-option-head">
                                <span>
                                    <h3>{{ $variant->name }}</h3>
                                    <span class="table-secondary">{{ $variant->product->name }} · {{ $available }} disponibles</span>
                                </span>
                                <span class="sale-price">{{ \App\Support\Money::format($variant->price_cents) }}</span>
                            </div>
                            <div class="form-group">
                                <label for="sale-qty-{{ $variant->id }}">Cantidad</label>
                                <input id="sale-qty-{{ $variant->id }}"
                                       type="number" min="0" max="{{ $available }}" step="1"
                                       class="input"
                                       wire:model.live.debounce.400ms="quantities.{{ $variant->id }}">
                                @error('quantities.'.$variant->id) <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                            <div class="sale-subtotal">
                                <span>Subtotal</span>
                                <strong>{{ \App\Support\Money::format($variant->price_cents * $quantity) }}</strong>
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('quantities') <p class="field-error" style="margin-top:12px">{{ $message }}</p> @enderror

                <div class="form-grid" style="margin-top:18px">
                    <div class="form-group">
                        <label for="sold-at">Fecha y hora</label>
                        <input id="sold-at" type="datetime-local" class="input" wire:model="soldAt">
                    </div>
                    <div class="form-group">
                        <label for="sale-notes">Nota</label>
                        <input id="sale-notes" class="input" wire:model="notes" placeholder="Opcional">
                    </div>
                </div>
                <div class="form-actions">
                    <div style="margin-right:auto">
                        <span class="field-hint">Total a cobrar</span>
                        <strong style="display:block;font:700 1.45rem Georgia,serif;margin-top:4px">
                            {{ \App\Support\Money::format($totalCents) }}
                        </strong>
                    </div>
                    <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">
                        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                        <span wire:loading.remove wire:target="recordSale">Confirmar venta</span>
                        <span wire:loading wire:target="recordSale">Registrando…</span>
                    </button>
                </div>
            </form>
        </section>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h2>Ventas recientes</h2>
                    <p>Últimos movimientos</p>
                </div>
            </div>
            <div class="panel-body stock-list">
                @forelse ($recentSales as $sale)
                    <div class="stock-row">
                        <div class="stock-name">
                            <span class="avatar">{{ $sale->items->sum('quantity') }}</span>
                            <span>
                                <strong>{{ $sale->number }}</strong>
                                <small>{{ $sale->items->map(fn ($item) => $item->variant_name ?: $item->productVariant->name)->join(', ') }} · {{ $sale->sold_at->translatedFormat('d M, H:i') }}</small>
                            </span>
                        </div>
                        <span class="amount">{{ \App\Support\Money::format($sale->total_cents) }}</span>
                    </div>
                @empty
                    <div class="empty-state">
                        <strong>Sin ventas registradas</strong>
                        <p>La primera venta aparecerá aquí.</p>
                    </div>
                @endforelse
            </div>
        </aside>
    </div>
</div>
