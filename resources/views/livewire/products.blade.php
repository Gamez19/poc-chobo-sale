<div>
    <header class="page-header">
        <div>
            <p class="eyebrow">Catálogo & recetas</p>
            <h1>Productos y precios</h1>
            <p class="page-description">Cada categoría tiene su propio precio, receta y existencia, sin alterar el historial de ventas.</p>
        </div>
    </header>

    @if ($statusMessage)
        <div class="toast-success" role="status" data-testid="products-status" style="margin-bottom:16px">
            {{ $statusMessage }}
        </div>
    @endif

    <div class="panel-grid" style="margin-bottom:24px">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Nuevo producto</h2>
                    <p>Crea una familia de productos</p>
                </div>
            </div>
            <form class="panel-body" wire:submit="createProduct">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="product-name">Nombre</label>
                        <input id="product-name" class="input" wire:model="newProductName" placeholder="Ej. Chocobanano">
                        @error('newProductName') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="product-description">Descripción</label>
                        <input id="product-description" class="input" wire:model="newProductDescription" placeholder="Opcional">
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-secondary" type="submit">Crear producto</button></div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Nueva variante</h2>
                    <p>Define categoría, SKU y precio</p>
                </div>
            </div>
            <form class="panel-body" wire:submit="createVariant">
                <div class="form-grid cols-3">
                    <div class="form-group">
                        <label for="variant-product">Producto</label>
                        <select id="variant-product" class="select" wire:model="newVariantProductId">
                            <option value="">Seleccionar</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                        @error('newVariantProductId') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="variant-name">Categoría</label>
                        <input id="variant-name" class="input" wire:model="newVariantName" placeholder="Ej. Maní">
                        @error('newVariantName') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="variant-sku">SKU</label>
                        <input id="variant-sku" class="input" wire:model="newVariantSku" placeholder="CHO-MANI">
                        <span class="field-hint">Si lo dejas vacío se genera automáticamente con el prefijo CHO- y el nombre del producto.</span>
                        @error('newVariantSku') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-group">
                        <label for="variant-price">Precio</label>
                        <div class="input-with-prefix">
                            <span>C$</span>
                            <input id="variant-price" type="number" min="0.01" step="0.01" class="input" wire:model="newVariantPrice" placeholder="20.00">
                        </div>
                        @error('newVariantPrice') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">Agregar variante</button></div>
            </form>
        </section>
    </div>

    <div class="product-stack">
        @forelse ($products as $product)
            <article class="product-card">
                <header class="product-card-head">
                    <div>
                        <h2>{{ $product->name }}</h2>
                        <p>{{ $product->description ?: 'Sin descripción' }}</p>
                    </div>
                    <span class="badge neutral">{{ $product->variants->count() }} {{ Str::plural('variante', $product->variants->count()) }}</span>
                </header>

                @if ($product->variants->isEmpty())
                    <div class="empty-state">
                        <strong>Este producto aún no tiene variantes</strong>
                        <p>Usa el formulario superior para agregar la primera.</p>
                    </div>
                @else
                    <div class="variant-grid">
                        @foreach ($product->variants as $variant)
                            <section class="variant-card" wire:key="variant-{{ $variant->id }}">
                                    <div class="variant-top">
                                        <div>
                                            <h3>{{ $variant->name }}</h3>
                                            <small>{{ $variant->sku }}</small>
                                        </div>
                                        <span class="badge {{ ($variant->available_stock ?? 0) > 0 ? 'success' : 'warning' }}">
                                            {{ $variant->available_stock ?? 0 }} disponibles
                                        </span>
                                    </div>

                                    <div class="variant-top">
                                        <span class="badge {{ $variant->active && ! $variant->deleted_at ? 'success' : 'neutral' }}" data-testid="variant-state-{{ $variant->id }}">
                                            {{ $variant->deleted_at ? 'Eliminada' : ($variant->active ? 'Activa' : 'Inactiva') }}
                                        </span>
                                        @unless ($variant->active && ! $variant->deleted_at)
                                            <button class="btn btn-soft btn-sm" type="button" wire:click="restoreVariant({{ $variant->id }})">
                                                Restaurar
                                            </button>
                                        @endunless
                                    </div>

                                    @if ($variant->deleted_at)
                                        <p class="table-secondary" data-testid="variant-archived-price-{{ $variant->id }}">
                                            Precio histórico: {{ \App\Support\Money::format($variant->price_cents) }}
                                        </p>
                                    @else
                                        <div class="form-group">
                                            <label for="variant-name-{{ $variant->id }}">Nombre de la variante</label>
                                        <div class="price-row">
                                            <input id="variant-name-{{ $variant->id }}" class="input"
                                                   wire:model="variantNames.{{ $variant->id }}">
                                            <button class="btn btn-soft btn-sm" type="button" wire:click="renameVariant({{ $variant->id }})">Renombrar</button>
                                            <button class="btn btn-soft btn-sm" type="button"
                                                    wire:click="removeVariant({{ $variant->id }})"
                                                    wire:confirm="¿Eliminar esta variante? Se conservará en el historial.">Eliminar</button>
                                        </div>
                                            @error('variantNames.'.$variant->id) <span class="field-error">{{ $message }}</span> @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="price-{{ $variant->id }}">Precio de venta</label>
                                            <div class="price-row">
                                                <div class="input-with-prefix">
                                                    <span>C$</span>
                                                    <input id="price-{{ $variant->id }}" type="number" min="0.01" step="0.01" class="input" wire:model="prices.{{ $variant->id }}">
                                                </div>
                                                <button class="btn btn-soft btn-sm" type="button" wire:click="savePrice({{ $variant->id }})">Guardar</button>
                                            </div>
                                            @error('prices.'.$variant->id) <span class="field-error">{{ $message }}</span> @enderror
                                        </div>

                                        @php
                                            $activeRecipeItems = $variant->recipeItems->whereNull('deleted_at');
                                            $variantMaterials = $materials->filter(fn ($material) => (! $material->deleted_at && $material->active)
                                                || $activeRecipeItems->contains('raw_material_id', $material->id));
                                        @endphp
                                        <details class="recipe">
                                            <summary>Configurar receta · {{ $activeRecipeItems->count() }} insumos</summary>
                                        <div class="recipe-fields">
                                            @foreach ($variantMaterials as $material)
                                                <div class="mini-field">
                                                    <label for="recipe-{{ $variant->id }}-{{ $material->id }}">
                                                        {{ $material->name }}
                                                        <span class="field-hint">({{ $material->unit }})</span>
                                                        @unless ($material->active && ! $material->deleted_at)
                                                            <span class="badge neutral"
                                                                  data-testid="recipe-material-inactive-{{ $variant->id }}-{{ $material->id }}">
                                                                Inactiva · pon 0 y guarda para quitarla
                                                            </span>
                                                        @endunless
                                                    </label>
                                                <input id="recipe-{{ $variant->id }}-{{ $material->id }}"
                                                       type="number" min="0" step="0.001" class="input"
                                                       wire:model="recipeQuantities.{{ $variant->id }}.{{ $material->id }}"
                                                       placeholder="0">
                                                    </div>
                                                @endforeach
                                                <button class="btn btn-secondary btn-sm w-full" type="button" wire:click="saveRecipe({{ $variant->id }})">
                                                    Guardar receta
                                                </button>
                                            </div>
                                        </details>
                                    @endif
                                </section>
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <div class="panel empty-state">
                <strong>No hay productos registrados</strong>
                <p>Crea el primero con el formulario superior.</p>
            </div>
        @endforelse
    </div>
</div>

