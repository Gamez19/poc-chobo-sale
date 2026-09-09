<div>
    <header class="page-header">
        <div>
            <p class="eyebrow">Abastecimiento</p>
            <h1>Materias primas</h1>
            <p class="page-description">Controla cantidades, costos promedio y niveles mínimos de cada insumo.</p>
        </div>
    </header>

    <div class="split-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Inventario de insumos</h2>
                    <p>{{ $materials->total() }} materias primas registradas</p>
                </div>
            </div>
            <div class="panel-body" style="padding-bottom:10px">
                <div class="search-box">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                    <input type="search" class="input" wire:model.live.debounce.300ms="search" placeholder="Buscar materia prima…">
                </div>
            </div>
            <div class="table-wrap desktop-only">
                <table class="data-table">
                    <thead>
                        <tr><th>Materia prima</th><th>Existencia</th><th>Costo unitario</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse ($materials as $material)
                        @php
                            $isLow = $material->isLowStock();
                            $target = max((float) $material->minimum_stock * 2, 1);
                            $progress = min(100, ((float) $material->stock_quantity / $target) * 100);
                        @endphp
                        <tr>
                            <td>
                                <span class="table-primary">{{ $material->name }}</span>
                                    <span class="table-secondary">Mínimo: {{ number_format($material->minimum_stock, 2) }} {{ $material->unit }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $isLow ? 'danger' : 'success' }}">
                                    {{ number_format($material->stock_quantity, 2) }} {{ $material->unit }}
                                </span>
                                <div @class(['progress', 'is-low' => $isLow])><span style="width:{{ $progress }}%"></span></div>
                            </td>
                            <td class="amount">{{ \App\Support\Money::format($material->unit_cost_cents) }}<span class="table-secondary">por {{ $material->unit }}</span></td>
                            <td class="text-right">
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="openEdit({{ $material->id }})">Editar</button>
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="openRestock({{ $material->id }})">Agregar entrada</button>
                                </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty-state"><strong>No se encontraron insumos</strong><p>Agrega una materia prima usando el formulario.</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mobile-card-list mobile-only">
                @forelse ($materials as $material)
                    @php
                        $isLow = $material->isLowStock();
                        $target = max((float) $material->minimum_stock * 2, 1);
                        $progress = min(100, ((float) $material->stock_quantity / $target) * 100);
                    @endphp
                    <article class="mobile-data-card">
                        <div class="mobile-data-card-head">
                            <strong>{{ $material->name }}</strong>
                        <span class="badge {{ $isLow ? 'danger' : 'success' }}">{{ number_format($material->stock_quantity, 2) }} {{ $material->unit }}</span>
                        </div>
                        <small>Mínimo: {{ number_format($material->minimum_stock, 2) }} {{ $material->unit }} · {{ \App\Support\Money::format($material->unit_cost_cents) }} por {{ $material->unit }}</small>
                        <div @class(['progress', 'is-low' => $isLow])><span style="width:{{ $progress }}%"></span></div>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="openEdit({{ $material->id }})">Editar</button>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="openRestock({{ $material->id }})">Agregar entrada</button>
                    </article>
                @empty
                    <div class="empty-state"><strong>No se encontraron insumos</strong><p>Agrega una materia prima usando el formulario.</p></div>
                @endforelse
            </div>
            <div class="panel-body pagination-wrap">{{ $materials->links() }}</div>
        </section>

        <aside class="panel">
            <div class="panel-header">
                <div>
                    <h2>Nueva materia prima</h2>
                    <p>Registra el insumo y su inventario inicial</p>
                </div>
            </div>
            <form class="panel-body" wire:submit="createMaterial">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="material-name">Nombre</label>
                        <input id="material-name" class="input" wire:model="name" placeholder="Ej. Chocolate para cobertura">
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="unit">Unidad</label>
                        <select id="unit" class="select" wire:model="unit">
                            <option value="unidad">Unidad</option>
                            <option value="gramos">Gramos</option>
                            <option value="kilogramos">Kilogramos</option>
                            <option value="mililitros">Mililitros</option>
                            <option value="paquete">Paquete</option>
                            <option value="libra">Libra</option>
                            <option value="onzas">Onzas</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="minimum">Nivel mínimo</label>
                        <input id="minimum" type="number" min="0" step="0.01" class="input" wire:model="minimumStock">
                        @error('minimumStock') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="initial-quantity">Cantidad inicial</label>
                        <input id="initial-quantity" type="number" min="0" step="0.01" class="input" wire:model="initialQuantity">
                        @error('initialQuantity') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="initial-cost">Costo por unidad</label>
                        <div class="input-with-prefix">
                            <span>C$</span>
                            <input id="initial-cost" type="number" min="0" step="0.01" class="input" wire:model="initialUnitCost">
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="createMaterial">Guardar insumo</span>
                        <span wire:loading wire:target="createMaterial">Guardando…</span>
                    </button>
                </div>
            </form>
        </aside>
    </div>

    @if ($editMaterialId && $editingMaterial)
        <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="edit-title" wire:click.self="$set('editMaterialId', null)">
            <form class="modal" wire:submit="updateMaterial">
                <div class="modal-head">
                    <div>
                        <h2 id="edit-title">Editar materia prima</h2>
                        <p>{{ $editingMaterial->name }} · {{ number_format($editingMaterial->stock_quantity, 2) }} {{ $editingMaterial->unit }} actuales</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('editMaterialId', null)" aria-label="Cerrar">Cerrar</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="edit-name">Nombre</label>
                            <input id="edit-name" class="input" wire:model="editName" autofocus>
                            @error('editName') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="edit-unit">Unidad</label>
                            <select id="edit-unit" class="select" wire:model="editUnit">
                                @foreach (\App\Models\RawMaterial::UNITS as $availableUnit)
                                    <option value="{{ $availableUnit }}">{{ ucfirst($availableUnit) }}</option>
                                @endforeach
                            </select>
                            @error('editUnit') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="edit-minimum">Nivel mínimo</label>
                            <input id="edit-minimum" type="number" min="0" step="0.01" class="input" wire:model="editMinimumStock">
                            @error('editMinimumStock') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <p class="table-secondary">La existencia y el costo unitario solo cambian con entradas de inventario.</p>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" wire:click="$set('editMaterialId', null)">Cancelar</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Guardar cambios</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    @if ($restockMaterialId && $selectedMaterial)
        <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="restock-title" wire:click.self="$set('restockMaterialId', null)">
            <form class="modal" wire:submit="restock">
                <div class="modal-head">
                    <div>
                        <h2 id="restock-title">Agregar entrada</h2>
                        <p>{{ $selectedMaterial->name }} · {{ number_format($selectedMaterial->stock_quantity, 2) }} {{ $selectedMaterial->unit }} actuales</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="$set('restockMaterialId', null)" aria-label="Cerrar">Cerrar</button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="restock-quantity">Cantidad recibida</label>
                            <input id="restock-quantity" type="number" min="0.01" step="0.01" class="input" wire:model="restockQuantity" autofocus>
                            @error('restockQuantity') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-group">
                            <label for="restock-cost">Costo por {{ $selectedMaterial->unit }}</label>
                            <div class="input-with-prefix">
                                <span>C$</span>
                                <input id="restock-cost" type="number" min="0" step="0.01" class="input" wire:model="restockUnitCost">
                            </div>
                        </div>
                        <div class="form-group full">
                            <label for="restock-notes">Nota opcional</label>
                            <textarea id="restock-notes" class="textarea" wire:model="restockNotes" placeholder="Proveedor, factura o comentario…"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" wire:click="$set('restockMaterialId', null)">Cancelar</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Registrar entrada</button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
