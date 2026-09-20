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
                    <tr><th>Lote</th><th>Producción</th><th>Contenido</th><th>Disponible</th><th>Estado</th><th></th></tr>
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
                        <td class="text-right">
                            <button type="button" class="btn btn-soft btn-sm" wire:click="openEdit({{ $lot->id }})">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state"><strong>Aún no hay lotes</strong><p>Registra la primera producción.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($editLotId && $editingLot)
        <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="edit-lot-title" wire:click.self="closeEdit">
            <form class="modal" wire:submit="updateLot">
                <div class="modal-head">
                    <div>
                        <h2 id="edit-lot-title">Editar lote de producción</h2>
                        <p>Ajusta las variantes existentes sin cambiar las asignaciones de ventas.</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeEdit" aria-label="Cerrar">Cerrar</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit-lot-code">Código de lote</label>
                            <input id="edit-lot-code" class="input" wire:model="editCode" autofocus>
                            @error('editCode') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="edit-lot-produced-at">Fecha de producción</label>
                            <input id="edit-lot-produced-at" type="date" class="input" wire:model="editProducedAt">
                            @error('editProducedAt') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="edit-lot-expires-at">Fecha de vencimiento</label>
                            <input id="edit-lot-expires-at" type="date" class="input" wire:model="editExpiresAt">
                            @error('editExpiresAt') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group full">
                            <label for="edit-lot-notes">Notas</label>
                            <textarea id="edit-lot-notes" class="textarea" wire:model="editNotes"></textarea>
                            @error('editNotes') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="section-heading">
                        <div>
                            <h3>Unidades producidas</h3>
                            <p>Solo se pueden ajustar las variantes que ya pertenecen a este lote.</p>
                        </div>
                    </div>
                    <div class="lot-grid">
                        @foreach ($editingLot->items as $item)
                            <div class="quantity-card" wire:key="edit-lot-item-{{ $item->id }}">
                                <div class="variant-meta">
                                    <span>
                                        <strong>{{ $item->productVariant->name }}</strong>
                                        <small>{{ $item->productVariant->product->name }}</small>
                                    </span>
                                    <span class="badge neutral">{{ $item->quantity_available }} disponibles</span>
                                </div>
                                <input type="number" min="0" step="1" class="input"
                                       aria-label="Cantidad producida de {{ $item->productVariant->name }}"
                                       wire:model="editVariantQuantities.{{ $item->product_variant_id }}">
                                @error('editVariantQuantities.'.$item->product_variant_id) <span class="field-error">{{ $message }}</span> @enderror
                            </div>
                        @endforeach
                    </div>
                    @error('editVariantQuantities') <p class="field-error" style="margin-top:10px">{{ $message }}</p> @enderror

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeEdit">Cancelar</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="updateLot">Guardar cambios</span>
                            <span wire:loading wire:target="updateLot">Guardando…</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>

