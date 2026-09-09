<div>
    <header class="page-header">
        <div>
            <p class="eyebrow">Abastecimiento</p>
            <h1>Materias primas</h1>
            <p class="page-description">Controla cantidades, costos promedio y niveles mínimos de cada insumo.</p>
        </div>
    </header>

    <div class="split-grid is-form-first">
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
            <ul class="material-list" role="list">
                @forelse ($materials as $material)
                    @php
                        $isLow = $material->isLowStock();
                        $target = max((float) $material->minimum_stock * 2, 1);
                        $progress = min(100, ((float) $material->stock_quantity / $target) * 100);
                        $stockLabel = number_format($material->stock_quantity, 2).' '.$material->unit;
                        $progressWidth = ['width: '.$progress.'%'];
                        $minimumLabel = number_format($material->minimum_stock, 2).' '.$material->unit;
                        $rowLabel = 'Ver detalle de '.$material->name.': '.$stockLabel.' en existencia, mínimo '.$minimumLabel;
                    @endphp
                    <li wire:key="material-{{ $material->id }}">
                        <button type="button"
                                class="material-row"
                                wire:click="openDetail({{ $material->id }})"
                                aria-haspopup="dialog"
                                aria-label="{{ $rowLabel }}">
                            <span class="material-row-main">
                                <strong>{{ $material->name }}</strong>
                                <small>Mínimo: {{ $minimumLabel }}</small>
                            </span>
                            <span class="material-row-stock">
                                <span class="badge {{ $isLow ? 'danger' : 'success' }}">{{ $stockLabel }}</span>
                                <span @class(['progress', 'is-low' => $isLow]) aria-hidden="true">
                                    <span @style($progressWidth)></span>
                                </span>
                            </span>
                        </button>
                    </li>
                @empty
                    <li><div class="empty-state"><strong>No se encontraron insumos</strong><p>Agrega una materia prima usando el formulario.</p></div></li>
                @endforelse
            </ul>
            <div class="panel-body pagination-wrap">{{ $materials->links() }}</div>
        </section>
    </div>

    @if ($detailMaterialId && $detailMaterial)
        <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="detail-title" wire:click.self="closeDetail">
            <div class="modal">
                <div class="modal-head">
                    <div>
                        <h2 id="detail-title">Detalle de la materia prima</h2>
                        <p>{{ $detailMaterial->name }}</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeDetail" aria-label="Cerrar">Cerrar</button>
                </div>
                <div class="modal-body">
                    <dl class="mobile-data-grid">
                        <div>
                            <dt>Existencia</dt>
                            <dd>{{ number_format($detailMaterial->stock_quantity, 2) }} {{ $detailMaterial->unit }}</dd>
                        </div>
                        <div>
                            <dt>Nivel mínimo</dt>
                            <dd>{{ number_format($detailMaterial->minimum_stock, 2) }} {{ $detailMaterial->unit }}</dd>
                        </div>
                        <div>
                            <dt>Costo unitario</dt>
                            <dd>{{ \App\Support\Money::format($detailMaterial->unit_cost_cents) }}</dd>
                        </div>
                    </dl>
                    <p class="table-secondary">La existencia y el costo unitario solo cambian con entradas de inventario.</p>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" wire:click="openEdit({{ $detailMaterial->id }})">Editar</button>
                        <button type="button" class="btn btn-primary" wire:click="openRestock({{ $detailMaterial->id }})">Agregar entrada</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($editMaterialId && $editingMaterial)
        <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="edit-title" wire:click.self="closeEdit">
            <form class="modal" wire:submit="updateMaterial">
                <div class="modal-head">
                    <div>
                        <h2 id="edit-title">Editar materia prima</h2>
                        <p>{{ $editingMaterial->name }} · {{ number_format($editingMaterial->stock_quantity, 2) }} {{ $editingMaterial->unit }} actuales</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeEdit" aria-label="Cerrar">Cerrar</button>
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
                        <button type="button" class="btn btn-secondary" wire:click="closeEdit">Cancelar</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Guardar cambios</button>
                    </div>
                </div>
            </form>
        </div>
    @endif

    @if ($restockMaterialId && $selectedMaterial)
        <div class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="restock-title" wire:click.self="closeRestock">
            <form class="modal" wire:submit="restock">
                <div class="modal-head">
                    <div>
                        <h2 id="restock-title">Agregar entrada</h2>
                        <p>{{ $selectedMaterial->name }} · {{ number_format($selectedMaterial->stock_quantity, 2) }} {{ $selectedMaterial->unit }} actuales</p>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeRestock" aria-label="Cerrar">Cerrar</button>
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
                        <button type="button" class="btn btn-secondary" wire:click="closeRestock">Cancelar</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Registrar entrada</button>
                    </div>
                </div>
            </form>
        </div>
    @endif
</div>
