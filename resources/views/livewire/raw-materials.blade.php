<div>
    <header class="page-header">
        <div>
            <p class="eyebrow">Abastecimiento</p>
            <h1>Materias primas y compras</h1>
            <p class="page-description">Registra cuánto gastaste en cada compra y marca los insumos que compraste.</p>
        </div>
    </header>

    <div class="split-grid is-form-first">
        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h2>Registrar compra</h2>
                    <p>Total gastado y checklist de insumos</p>
                </div>
            </div>
            <form class="panel-body" wire:submit="createPurchase">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="purchase-date">Fecha</label>
                        <input id="purchase-date" type="date" class="input" wire:model="purchasedAt" max="{{ today()->format('Y-m-d') }}">
                        @error('purchasedAt') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="purchase-total">Total gastado</label>
                        <div class="input-with-prefix">
                            <span>C$</span>
                            <input id="purchase-total" type="number" min="0.01" step="0.01" class="input" wire:model="purchaseTotal" placeholder="0.00">
                        </div>
                        @error('purchaseTotal') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <fieldset class="form-group full checklist-group">
                        <legend>Insumos comprados</legend>
                        @if ($checklistMaterials->isEmpty())
                            <p class="field-hint">Agrega insumos al catálogo para marcarlos aquí.</p>
                        @else
                            <div class="checklist">
                                @foreach ($checklistMaterials as $material)
                                    <label class="checklist-item" wire:key="purchase-material-{{ $material->id }}">
                                        <input type="checkbox" value="{{ $material->id }}" wire:model="purchaseMaterialIds">
                                        <span>{{ $material->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('purchaseMaterialIds.*') <span class="field-error">{{ $message }}</span> @enderror
                    </fieldset>
                    <div class="form-group full">
                        <label for="purchase-notes">Nota opcional</label>
                        <textarea id="purchase-notes" class="textarea" wire:model="purchaseNotes" placeholder="Ej. 5 docenas de bananos y 3 bolsas de chocolate"></textarea>
                        @error('purchaseNotes') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="createPurchase">Guardar compra</span>
                        <span wire:loading wire:target="createPurchase">Guardando…</span>
                    </button>
                </div>
            </form>
        </aside>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Historial de compras</h2>
                    <p>Gastado este mes: {{ \App\Support\Money::format($monthSpentCents) }}</p>
                </div>
                <span class="badge neutral">{{ $purchases->total() }} compras</span>
            </div>
            <ul class="material-list" role="list">
                @forelse ($purchases as $purchase)
                    <li wire:key="purchase-{{ $purchase->id }}">
                        <div class="material-row is-static">
                            <span class="material-row-main">
                                <strong>{{ $purchase->rawMaterials->isEmpty() ? 'Sin insumos marcados' : $purchase->rawMaterials->pluck('name')->join(', ') }}</strong>
                                <small>{{ $purchase->purchased_at->translatedFormat('d M Y') }}@if ($purchase->notes) · {{ $purchase->notes }}@endif</small>
                            </span>
                            <span class="material-row-stock">
                                <span class="badge warning">{{ \App\Support\Money::format($purchase->total_cents) }}</span>
                                <button type="button" class="btn btn-secondary btn-sm"
                                        wire:click="deletePurchase({{ $purchase->id }})"
                                        wire:confirm="¿Eliminar esta compra?">Eliminar</button>
                            </span>
                        </div>
                    </li>
                @empty
                    <li><div class="empty-state"><strong>Todavía no registras compras</strong><p>Usa el formulario para anotar lo que gastaste.</p></div></li>
                @endforelse
            </ul>
            <div class="panel-body pagination-wrap">{{ $purchases->links() }}</div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Catálogo de insumos</h2>
                <p>{{ $materials->total() }} insumos registrados</p>
            </div>
        </div>
        <div class="panel-body">
            <form class="inline-form" wire:submit="createMaterial">
                <div class="form-group">
                    <label for="material-name">Nuevo insumo</label>
                    <input id="material-name" class="input" wire:model="name" placeholder="Ej. Chocolate para cobertura">
                    @error('name') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Agregar insumo</button>
            </form>
            <div class="search-box" style="margin-top:14px">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input type="search" class="input" wire:model.live.debounce.300ms="search" placeholder="Buscar insumo…">
            </div>
        </div>
        <ul class="material-list" role="list">
            @forelse ($materials as $material)
                <li wire:key="material-{{ $material->id }}">
                    <button type="button"
                            class="material-row"
                            wire:click="openEdit({{ $material->id }})"
                            aria-haspopup="dialog"
                            aria-label="Editar {{ $material->name }}">
                        <span class="material-row-main">
                            <strong>{{ $material->name }}</strong>
                        </span>
                        <span class="material-row-stock">
                            <span class="badge neutral">Editar</span>
                        </span>
                    </button>
                </li>
            @empty
                <li><div class="empty-state"><strong>No se encontraron insumos</strong><p>Agrega un insumo por su nombre.</p></div></li>
            @endforelse
        </ul>
        <div class="panel-body pagination-wrap">{{ $materials->links() }}</div>
    </section>

    @if ($editMaterialId && $editingMaterial)
        <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="edit-title" wire:click.self="closeEdit">
            <form class="modal" wire:submit="updateMaterial">
                <div class="modal-head">
                    <div>
                        <h2 id="edit-title">Editar insumo</h2>
                        <p>{{ $editingMaterial->name }}</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeEdit" aria-label="Cerrar">Cerrar</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-name">Nombre</label>
                        <input id="edit-name" class="input" wire:model="editName" autofocus>
                        @error('editName') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" wire:click="closeEdit">Cancelar</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Guardar cambios</button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
