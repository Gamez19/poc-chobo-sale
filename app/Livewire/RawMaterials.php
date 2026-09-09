<?php

namespace App\Livewire;

use App\Models\RawMaterial;
use App\Services\RawMaterialStockService;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Materias primas')]
class RawMaterials extends Component
{
    use WithPagination;

    public string $search = '';

    public string $name = '';

    public string $unit = 'unidad';

    public string $minimumStock = '10';

    public string $initialQuantity = '0';

    public string $initialUnitCost = '0';

    public ?int $restockMaterialId = null;

    public string $restockQuantity = '';

    public string $restockUnitCost = '';

    public string $restockNotes = '';

    public ?int $editMaterialId = null;

    public string $editName = '';

    public string $editUnit = 'unidad';

    public string $editMinimumStock = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function createMaterial(RawMaterialStockService $stockService): void
    {
        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('raw_materials', 'name')->whereNull('deleted_at'),
            ],
            'unit' => ['required', Rule::in(RawMaterial::UNITS)],
            'minimumStock' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'initialQuantity' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'initialUnitCost' => ['required', 'numeric', 'min:0'],
        ], [
            'name.required' => 'Escribe el nombre de la materia prima.',
            'name.unique' => 'Ya existe una materia prima con ese nombre.',
            'unit.in' => 'Selecciona una unidad válida.',
            'minimumStock.decimal' => 'Usa como máximo dos decimales.',
            'initialQuantity.decimal' => 'Usa como máximo dos decimales.',
        ]);

        $initialUnitCostCents = Money::fromDecimal($validated['initialUnitCost']);

        $material = RawMaterial::create([
            'name' => trim($validated['name']),
            'unit' => trim($validated['unit']),
            'minimum_stock' => $validated['minimumStock'],
            'unit_cost_cents' => $initialUnitCostCents,
        ]);

        if ((float) $validated['initialQuantity'] > 0) {
            $stockService->receive(
                $material,
                (float) $validated['initialQuantity'],
                $initialUnitCostCents,
                'Inventario inicial',
            );
        }

        $this->reset('name', 'initialQuantity', 'initialUnitCost');
        $this->initialQuantity = '0';
        $this->initialUnitCost = '0';
        session()->flash('success', 'Materia prima agregada correctamente.');
    }

    public function openRestock(int $materialId): void
    {
        $material = RawMaterial::findOrFail($materialId);
        $this->restockMaterialId = $material->id;
        $this->restockUnitCost = number_format($material->unit_cost_cents / 100, 2, '.', '');
        $this->restockQuantity = '';
        $this->restockNotes = '';
        $this->resetValidation();
    }

    public function openEdit(int $materialId): void
    {
        $material = RawMaterial::findOrFail($materialId);
        $this->editMaterialId = $material->id;
        $this->editName = $material->name;
        $this->editUnit = $material->unit;
        $this->editMinimumStock = number_format((float) $material->minimum_stock, 2, '.', '');
        $this->resetValidation();
    }

    public function updateMaterial(): void
    {
        $this->editName = trim($this->editName);
        $this->editUnit = trim($this->editUnit);

        $validated = $this->validate([
            'editMaterialId' => [
                'required',
                Rule::exists('raw_materials', 'id')->whereNull('deleted_at'),
            ],
            'editName' => [
                'required',
                'string',
                'max:120',
                Rule::unique('raw_materials', 'name')
                    ->ignore($this->editMaterialId)
                    ->whereNull('deleted_at'),
            ],
            'editUnit' => ['required', Rule::in(RawMaterial::UNITS)],
            'editMinimumStock' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ], [
            'editName.required' => 'Escribe el nombre de la materia prima.',
            'editName.unique' => 'Ya existe una materia prima con ese nombre.',
            'editUnit.in' => 'Selecciona una unidad válida.',
            'editMinimumStock.min' => 'El nivel mínimo no puede ser negativo.',
            'editMinimumStock.decimal' => 'Usa como máximo dos decimales.',
        ]);

        $material = RawMaterial::findOrFail($validated['editMaterialId']);
        $material->update([
            'name' => $validated['editName'],
            'unit' => $validated['editUnit'],
            'minimum_stock' => $validated['editMinimumStock'],
        ]);

        $this->reset('editMaterialId', 'editName', 'editUnit', 'editMinimumStock');
        session()->flash('success', 'Materia prima actualizada correctamente.');
    }

    public function restock(RawMaterialStockService $stockService): void
    {
        $validated = $this->validate([
            'restockMaterialId' => ['required', 'exists:raw_materials,id'],
            'restockQuantity' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'restockUnitCost' => ['required', 'numeric', 'min:0'],
            'restockNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'restockQuantity.gt' => 'La cantidad debe ser mayor que cero.',
            'restockQuantity.decimal' => 'Usa como máximo dos decimales.',
        ]);

        $stockService->receive(
            RawMaterial::findOrFail($validated['restockMaterialId']),
            (float) $validated['restockQuantity'],
            Money::fromDecimal($validated['restockUnitCost']),
            $validated['restockNotes'] ?: null,
        );

        $this->reset('restockMaterialId', 'restockQuantity', 'restockUnitCost', 'restockNotes');
        session()->flash('success', 'Entrada de inventario registrada.');
    }

    public function render()
    {
        return view('livewire.raw-materials', [
            'materials' => RawMaterial::query()
                ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
                ->with(['movements' => fn ($query) => $query->latest('occurred_at')->limit(1)])
                ->orderBy('name')
                ->paginate(10),
            'selectedMaterial' => $this->restockMaterialId
                ? RawMaterial::find($this->restockMaterialId)
                : null,
            'editingMaterial' => $this->editMaterialId
                ? RawMaterial::find($this->editMaterialId)
                : null,
        ]);
    }
}
