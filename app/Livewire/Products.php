<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RecipeItem;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Productos y precios')]
class Products extends Component
{
    public array $prices = [];

    public array $recipeQuantities = [];

    public array $variantNames = [];

    public ?string $statusMessage = null;

    public string $newProductName = '';

    public string $newProductDescription = '';

    public ?int $newVariantProductId = null;

    public string $newVariantName = '';

    public string $newVariantSku = '';

    public string $newVariantPrice = '';

    public function mount(): void
    {
        $this->newVariantProductId = Product::query()->value('id');
        $this->syncInputs();
    }

    public function createProduct(): void
    {
        $validated = $this->validate([
            'newProductName' => ['required', 'string', 'max:120'],
            'newProductDescription' => ['nullable', 'string', 'max:500'],
        ]);

        $baseSlug = Str::slug($validated['newProductName']);
        $slug = $baseSlug;
        $sequence = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$sequence++;
        }

        $product = Product::create([
            'name' => trim($validated['newProductName']),
            'slug' => $slug,
            'description' => $validated['newProductDescription'] ?: null,
        ]);

        $this->reset('newProductName', 'newProductDescription');
        $this->newVariantProductId = $product->id;
        $this->statusMessage = 'Producto creado. Ahora puedes agregarle variantes.';
    }

    public function createVariant(): void
    {
        $this->newVariantName = trim($this->newVariantName);
        $this->newVariantSku = Str::upper(trim($this->newVariantSku));

        $validated = $this->validate([
            'newVariantProductId' => [
                'required',
                Rule::exists('products', 'id')->whereNull('deleted_at'),
            ],
            'newVariantName' => [
                'required',
                'string',
                'max:80',
                Rule::unique('product_variants', 'name')
                    ->where(fn ($query) => $query
                        ->where('product_id', $this->newVariantProductId)
                        ->whereNull('deleted_at')),
            ],
            'newVariantSku' => [
                'required',
                'string',
                'max:60',
                Rule::unique('product_variants', 'sku')->whereNull('deleted_at'),
            ],
            'newVariantPrice' => ['required', 'numeric', 'gt:0'],
        ], [
            'newVariantName.unique' => 'Ese producto ya tiene una variante con ese nombre.',
        ]);

        ProductVariant::create([
            'product_id' => $validated['newVariantProductId'],
            'name' => $validated['newVariantName'],
            'sku' => $validated['newVariantSku'],
            'price_cents' => Money::fromDecimal($validated['newVariantPrice']),
        ]);

        $this->reset('newVariantName', 'newVariantSku', 'newVariantPrice');
        $this->syncInputs();
        $this->statusMessage = 'Variante agregada correctamente.';
    }

    public function renameVariant(int $variantId): void
    {
        $variant = ProductVariant::findOrFail($variantId);

        $this->variantNames[$variantId] = trim((string) ($this->variantNames[$variantId] ?? ''));

        $this->validate([
            "variantNames.$variantId" => [
                'required',
                'string',
                'max:80',
                Rule::unique('product_variants', 'name')
                    ->where(fn ($query) => $query
                        ->where('product_id', $variant->product_id)
                        ->whereNull('deleted_at'))
                    ->ignore($variant->id),
            ],
        ], [
            "variantNames.$variantId.required" => 'El nombre de la variante es obligatorio.',
            "variantNames.$variantId.max" => 'El nombre no puede tener más de 80 caracteres.',
            "variantNames.$variantId.unique" => 'Ese producto ya tiene una variante con ese nombre.',
        ]);

        $variant->update(['name' => $this->variantNames[$variantId]]);

        $this->statusMessage = 'Nombre de la variante actualizado.';
    }

    public function removeVariant(int $variantId): void
    {
        $variant = ProductVariant::withTrashed()->findOrFail($variantId);

        DB::transaction(function () use ($variant): void {
            RecipeItem::withTrashed()
                ->where('product_variant_id', $variant->id)
                ->get()
                ->each(fn (RecipeItem $item): ?bool => $item->delete());
            $variant->update(['active' => false]);
            $variant->delete();
        });

        $this->syncInputs();
        $this->statusMessage = 'Variante eliminada y conservada en el historial.';
    }

    public function restoreVariant(int $variantId): void
    {
        $variant = ProductVariant::withTrashed()->findOrFail($variantId);

        DB::transaction(function () use ($variant): void {
            $variant->restore();
            $variant->update(['active' => true]);
            RecipeItem::withTrashed()
                ->where('product_variant_id', $variant->id)
                ->get()
                ->each(fn (RecipeItem $item): ?bool => $item->restore());
        });

        $this->syncInputs();
        $this->statusMessage = 'Variante reactivada.';
    }

    public function savePrice(int $variantId): void
    {
        $this->validate([
            "prices.$variantId" => ['required', 'numeric', 'gt:0'],
        ], [
            "prices.$variantId.gt" => 'El precio debe ser mayor que cero.',
        ]);

        ProductVariant::findOrFail($variantId)->update([
            'price_cents' => Money::fromDecimal($this->prices[$variantId]),
        ]);

        $this->statusMessage = 'Precio actualizado. Las ventas anteriores conservan su precio original.';
    }

    public function saveRecipe(int $variantId): void
    {
        ProductVariant::findOrFail($variantId);

        $this->validate([
            "recipeQuantities.$variantId.*" => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($variantId) {
            $activeMaterialIds = RawMaterial::query()
                ->where('active', true)
                ->pluck('id');
            $recipeMaterialIds = RecipeItem::withTrashed()
                ->where('product_variant_id', $variantId)
                ->pluck('raw_material_id');

            foreach ($activeMaterialIds->merge($recipeMaterialIds)->unique() as $materialId) {
                $quantity = (float) ($this->recipeQuantities[$variantId][$materialId] ?? 0);
                $items = RecipeItem::withTrashed()
                    ->where('product_variant_id', $variantId)
                    ->where('raw_material_id', $materialId)
                    ->get();

                if ($quantity > 0) {
                    $item = $items->first() ?? new RecipeItem([
                        'product_variant_id' => $variantId,
                        'raw_material_id' => $materialId,
                    ]);
                    $item->quantity_required = number_format($quantity, 3, '.', '');

                    if ($item->trashed()) {
                        $item->restore();
                    } else {
                        $item->save();
                    }
                } else {
                    $items->each(fn (RecipeItem $item): ?bool => $item->delete());
                }
            }
        });

        $this->syncInputs();
        $this->statusMessage = 'Receta actualizada.';
    }

    private function syncInputs(): void
    {
        ProductVariant::withTrashed()
            ->with(['recipeItems' => fn ($query) => $query->withTrashed()->with('rawMaterial')])
            ->get()
            ->each(function ($variant) {
                $this->prices[$variant->id] = number_format($variant->price_cents / 100, 2, '.', '');
                $this->variantNames[$variant->id] = $variant->name;

                foreach ($variant->recipeItems as $item) {
                    $this->recipeQuantities[$variant->id][$item->raw_material_id] = $item->trashed()
                        ? '0'
                        : rtrim(rtrim($item->quantity_required, '0'), '.');
                }
            });
    }

    public function render()
    {
        return view('livewire.products', [
            'products' => Product::query()
                ->with(['variants' => fn ($query) => $query
                    ->withTrashed()
                    ->with(['recipeItems' => fn ($recipeQuery) => $recipeQuery
                        ->withTrashed()
                        ->with('rawMaterial')])
                    ->withSum('availableLotItems as available_stock', 'quantity_available')
                    ->orderBy('name')])
                ->orderBy('name')
                ->get(),
            'materials' => RawMaterial::withTrashed()
                ->where(fn ($query) => $query
                    ->where(fn ($activeQuery) => $activeQuery
                        ->where('active', true)
                        ->whereNull('deleted_at'))
                    ->orWhereHas('recipeItems', fn ($recipeQuery) => $recipeQuery->withTrashed()))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
