<?php

namespace App\Livewire;

use App\Models\ProductVariant;
use App\Models\Sale;
use App\Services\SalesService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Ventas')]
class Sales extends Component
{
    public string $soldAt;

    public string $notes = '';

    public array $quantities = [];

    public string $lastSaleNumber = '';

    public function mount(): void
    {
        $this->soldAt = now()->format('Y-m-d\TH:i');
        $this->resetQuantities();
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
        return view('livewire.sales', [
            'variants' => $this->activeVariants()
                ->with('product')
                ->withSum('availableLotItems as available_stock', 'quantity_available')
                ->get(),
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
