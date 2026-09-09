<?php

namespace App\Livewire;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Services\SalesService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Ventas')]
class Sales extends Component
{
    public string $search = '';

    public string $soldAt;

    public string $notes = '';

    public array $quantities = [];

    public string $lastSaleNumber = '';

    public function mount(): void
    {
        $this->soldAt = now()->format('Y-m-d\TH:i');
        $this->resetQuantities();
    }

    /**
     * Sellable variants keep the total stable while the search filters the list.
     *
     * @return Collection<int, ProductVariant>
     */
    private function sellableVariants(): Collection
    {
        return $this->activeVariants()
            ->with('product')
            ->withSum('availableLotItems as available_stock', 'quantity_available')
            ->get()
            ->filter(fn (ProductVariant $variant): bool => (int) ($variant->available_stock ?? 0) > 0)
            ->values();
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, ProductVariant>
     */
    private function matchingVariants(Collection $variants): Collection
    {
        $search = trim($this->search);

        if ($search === '') {
            return $variants;
        }

        $needle = mb_strtolower($search);

        return $variants
            ->filter(fn (ProductVariant $variant): bool => str_contains(mb_strtolower($variant->name), $needle)
                || str_contains(mb_strtolower((string) $variant->product?->name), $needle))
            ->values();
    }

    public function recordSale(SalesService $salesService): void
    {
        $this->lastSaleNumber = '';

        $validated = $this->validate([
            'soldAt' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $sale = $salesService->record(
            $this->quantities,
            $validated['soldAt'],
            $validated['notes'] ?: null,
        );

        $this->reset('notes', 'quantities');
        $this->soldAt = now()->format('Y-m-d\TH:i');
        $this->resetQuantities();
        $this->lastSaleNumber = $sale->number;
        session()->flash('success', "Venta {$sale->number} registrada correctamente.");
    }

    public function render()
    {
        $sellableVariants = $this->sellableVariants();

        return view('livewire.sales', [
            'variants' => $sellableVariants,
            'visibleVariants' => $this->matchingVariants($sellableVariants),
            'totalCents' => $sellableVariants->sum(
                fn (ProductVariant $variant): int => $variant->price_cents * (int) ($this->quantities[$variant->id] ?? 0),
            ),
            'recentSales' => Sale::query()
                ->with('items.productVariant.product', 'items.lotAllocations.productionLotItem.productionLot')
                ->latest('sold_at')
                ->limit(8)
                ->get(),
        ]);
    }

    /**
     * Active variants ordered by product name, then variant name, so duplicate
     * variant names across products stay readable.
     *
     * @return Builder<ProductVariant>
     */
    private function activeVariants(): Builder
    {
        return ProductVariant::query()
            ->select('product_variants.*')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('product_variants.active', true)
            ->orderBy('products.name')
            ->orderBy('product_variants.name');
    }

    private function resetQuantities(): void
    {
        $this->quantities = $this->activeVariants()
            ->pluck('product_variants.id')
            ->mapWithKeys(fn ($id) => [(int) $id => 0])
            ->all();
    }
}
