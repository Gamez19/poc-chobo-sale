<?php

namespace App\Livewire;

use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Services\ProductionService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Lotes de producción')]
class ProductionLots extends Component
{
    public string $code = '';

    public string $producedAt;

    public string $expiresAt = '';

    public string $notes = '';

    public array $variantQuantities = [];

    public function mount(): void
    {
        $this->producedAt = today()->format('Y-m-d');
        ProductVariant::query()->pluck('id')->each(
            fn ($id) => $this->variantQuantities[$id] = 0
        );
    }

    public function createLot(ProductionService $productionService): void
    {
        $validated = $this->validate([
            'code' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('production_lots', 'code')->whereNull('deleted_at'),
            ],
            'producedAt' => ['required', 'date', 'before_or_equal:today'],
            'expiresAt' => ['nullable', 'date', 'after_or_equal:producedAt'],
            'notes' => ['nullable', 'string', 'max:500'],
            'variantQuantities.*' => ['nullable', 'integer', 'min:0'],
        ], [
            'producedAt.before_or_equal' => 'La fecha de producción no puede ser futura.',
            'expiresAt.after_or_equal' => 'La fecha de vencimiento debe ser posterior a la producción.',
        ]);

        $lot = $productionService->createLot([
            'code' => trim($validated['code'] ?? ''),
            'produced_at' => $validated['producedAt'],
            'expires_at' => $validated['expiresAt'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ], $this->variantQuantities);

        $this->reset('code', 'expiresAt', 'notes', 'variantQuantities');
        $this->producedAt = today()->format('Y-m-d');
        ProductVariant::query()->pluck('id')->each(
            fn ($id) => $this->variantQuantities[$id] = 0
        );
        session()->flash('success', "Lote {$lot->code} creado y materias primas descontadas.");
    }

    public function render()
    {
        return view('livewire.production-lots', [
            'variants' => ProductVariant::query()
                ->with('product')
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'lots' => ProductionLot::query()
                ->with('items.productVariant.product')
                ->latest('produced_at')
                ->latest('id')
                ->limit(12)
                ->get(),
        ]);
    }
}
