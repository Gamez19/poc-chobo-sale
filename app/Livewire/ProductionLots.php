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

    public ?int $editLotId = null;

    public string $editCode = '';

    public string $editProducedAt = '';

    public string $editExpiresAt = '';

    public string $editNotes = '';

    public array $editVariantQuantities = [];

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

    public function openEdit(int $lotId): void
    {
        $lot = ProductionLot::query()
            ->with('items.productVariant.product')
            ->findOrFail($lotId);

        $this->editLotId = $lot->id;
        $this->editCode = $lot->code;
        $this->editProducedAt = $lot->produced_at->format('Y-m-d');
        $this->editExpiresAt = $lot->expires_at?->format('Y-m-d') ?? '';
        $this->editNotes = $lot->notes ?? '';
        $this->editVariantQuantities = $lot->items
            ->mapWithKeys(fn ($item) => [$item->product_variant_id => $item->quantity_produced])
            ->all();
        $this->resetValidation();
    }

    public function closeEdit(): void
    {
        $this->reset(
            'editLotId',
            'editCode',
            'editProducedAt',
            'editExpiresAt',
            'editNotes',
            'editVariantQuantities',
        );
        $this->resetValidation();
    }

    public function updateLot(ProductionService $productionService): void
    {
        $this->editCode = trim($this->editCode);

        $validated = $this->validate([
            'editLotId' => [
                'required',
                Rule::exists('production_lots', 'id')->whereNull('deleted_at'),
            ],
            'editCode' => [
                'required',
                'string',
                'max:60',
                Rule::unique('production_lots', 'code')
                    ->ignore($this->editLotId)
                    ->whereNull('deleted_at'),
            ],
            'editProducedAt' => ['required', 'date', 'before_or_equal:today'],
            'editExpiresAt' => ['nullable', 'date', 'after_or_equal:editProducedAt'],
            'editNotes' => ['nullable', 'string', 'max:500'],
            'editVariantQuantities.*' => ['required', 'integer', 'min:0'],
        ], [
            'editCode.required' => 'El código del lote es obligatorio.',
            'editProducedAt.before_or_equal' => 'La fecha de producción no puede ser futura.',
            'editExpiresAt.after_or_equal' => 'La fecha de vencimiento debe ser posterior a la producción.',
        ]);

        $lot = $productionService->updateLot(
            ProductionLot::findOrFail($validated['editLotId']),
            [
                'code' => $validated['editCode'],
                'produced_at' => $validated['editProducedAt'],
                'expires_at' => $validated['editExpiresAt'] ?? null,
                'notes' => $validated['editNotes'] ?? null,
            ],
            $this->editVariantQuantities,
        );

        $this->closeEdit();
        session()->flash('success', "Lote {$lot->code} actualizado correctamente.");
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
            'editingLot' => $this->editLotId
                ? ProductionLot::query()
                    ->with('items.productVariant.product')
                    ->find($this->editLotId)
                : null,
        ]);
    }
}
