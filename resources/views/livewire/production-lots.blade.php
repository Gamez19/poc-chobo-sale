<div>
    <header class="page-header">
        <div>
            <p class="eyebrow">Producción</p>
            <h1>Lotes</h1>
            <p class="page-description">Agrega productos terminados por lote y descuenta automáticamente los insumos de cada receta.</p>
        </div>
    </header>

    <section class="panel" style="margin-bottom:22px">
        <div class="panel-header">
            <div>
                <h2>Nuevo lote de producción</h2>
                <p>El código se genera automáticamente si lo dejas vacío</p>
            </div>
        </div>
        <form class="panel-body" wire:submit="createLot">
            <div class="form-grid cols-3">
                <div class="form-group">
                    <label for="lot-code">Código de lote</label>
                    <input id="lot-code" class="input" wire:model="code" placeholder="Automático">
                    @error('code') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label for="produced-at">Fecha de producción</label>
                    <input id="produced-at" type="date" class="input" wire:model="producedAt">
                </div>
                <div class="form-group">
                    <label for="expires-at">Fecha de vencimiento</label>
                    <input id="expires-at" type="date" class="input" wire:model="expiresAt">
                    @error('expiresAt') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="section-heading">
                <div>
                    <h3>Unidades producidas</h3>
                    <p>Indica la cantidad por categoría</p>
                </div>
            </div>
            <div class="lot-grid">
                @foreach ($variants as $variant)
                    <div class="quantity-card" wire:key="lot-variant-{{ $variant->id }}">
                        <div class="variant-meta">
                            <span>
                                <strong>{{ $variant->name }}</strong>
                                <small>{{ $variant->product->name }}</small>
                            </span>
                            <span class="badge neutral">{{ $variant->sku }}</span>
                        </div>
                        <input type="number" min="0" step="1" class="input"
                               aria-label="Cantidad de {{ $variant->name }}"
                               wire:model="variantQuantities.{{ $variant->id }}">
                        @error('variantQuantities.'.$variant->id) <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                @endforeach
            </div>
            @error('variantQuantities') <p class="field-error" style="margin-top:10px">{{ $message }}</p> @enderror

            <div class="form-group" style="margin-top:15px">
                <label for="lot-notes">Notas</label>
                <textarea id="lot-notes" class="textarea" wire:model="notes" placeholder="Comentario opcional sobre la producción…"></textarea>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="createLot">Crear lote</span>
                    <span wire:loading wire:target="createLot">Procesando…</span>
                </button>
            </div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Historial de lotes</h2>
                <p>Últimos 12 lotes de producción</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Lote</th><th>Producción</th><th>Contenido</th><th>Disponible</th><th>Estado</th></tr>
                </thead>
                <tbody>
                @forelse ($lots as $lot)
                    <tr>
                        <td>
                            <span class="table-primary">{{ $lot->code }}</span>
                            <span class="table-secondary">{{ $lot->expires_at ? 'Vence '.$lot->expires_at->translatedFormat('d M Y') : 'Sin vencimiento' }}</span>
                        </td>
                        <td>{{ $lot->produced_at->translatedFormat('d M Y') }}</td>
                        <td>
                            @foreach ($lot->items as $item)
                                <span class="table-secondary">{{ $item->productVariant->name }}: {{ $item->quantity_produced }}</span>
                            @endforeach
                        </td>
                        <td class="amount">{{ $lot->items->sum('quantity_available') }} / {{ $lot->items->sum('quantity_produced') }}</td>
                        <td><span class="badge {{ $lot->status === 'open' ? 'success' : 'neutral' }}">{{ $lot->status === 'open' ? 'Activo' : 'Agotado' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty-state"><strong>Aún no hay lotes</strong><p>Registra la primera producción.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

