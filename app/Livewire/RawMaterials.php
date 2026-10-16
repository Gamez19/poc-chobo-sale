<?php

namespace App\Livewire;

use App\Models\Purchase;
use App\Models\RawMaterial;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
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

    public bool $isCreatingMaterial = false;

    public ?int $editMaterialId = null;

    public string $editName = '';

    public string $purchasedAt = '';

    public string $purchaseTotal = '';

    /** @var array<int, int|string> */
    public array $purchaseMaterialIds = [];

    public string $purchaseNotes = '';

    public function mount(): void
    {
        $this->purchasedAt = today()->format('Y-m-d');
    }

    public function openCreate(): void
    {
        $this->closeEdit();
        $this->reset('name');
        $this->resetValidation();
        $this->isCreatingMaterial = true;
    }

    public function closeCreate(): void
    {
        $this->reset('name', 'isCreatingMaterial');
        $this->resetValidation();
    }

    public function createMaterial(): void
    {
        $this->name = trim($this->name);

        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('raw_materials', 'name')->whereNull('deleted_at'),
            ],
        ], [
            'name.required' => 'El nombre del insumo es obligatorio.',
            'name.unique' => 'Ya existe un insumo con ese nombre.',
        ]);

        RawMaterial::create([
            'name' => $validated['name'],
            'unit' => 'unidad',
        ]);

        $this->closeCreate();
        session()->flash('success', 'Insumo agregado correctamente.');
    }

    public function openEdit(int $materialId): void
    {
        $material = RawMaterial::findOrFail($materialId);
        $this->reset('name', 'isCreatingMaterial');
        $this->editMaterialId = $material->id;
        $this->editName = $material->name;
        $this->resetValidation();
    }

    public function closeEdit(): void
    {
        $this->reset('editMaterialId', 'editName');
        $this->resetValidation();
    }

    public function updateMaterial(): void
    {
        $this->editName = trim($this->editName);

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
        ], [
            'editName.required' => 'El nombre del insumo es obligatorio.',
            'editName.unique' => 'Ya existe un insumo con ese nombre.',
        ]);

        RawMaterial::findOrFail($validated['editMaterialId'])->update([
            'name' => $validated['editName'],
        ]);

        $this->closeEdit();
        session()->flash('success', 'Insumo actualizado correctamente.');
    }

    public function createPurchase(): void
    {
        $validated = $this->validate([
            'purchasedAt' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'purchaseTotal' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'purchaseMaterialIds' => ['array'],
            'purchaseMaterialIds.*' => [
                'integer',
                'distinct',
                Rule::exists('raw_materials', 'id')->whereNull('deleted_at'),
            ],
            'purchaseNotes' => ['nullable', 'string', 'max:500'],
        ], [
            'purchasedAt.required' => 'La fecha de compra es obligatoria.',
            'purchasedAt.date_format' => 'La fecha de compra no es válida.',
            'purchasedAt.before_or_equal' => 'La fecha de compra no puede ser futura.',
            'purchaseTotal.required' => 'Ingresa el total gastado.',
            'purchaseTotal.gt' => 'El total debe ser mayor que cero.',
            'purchaseTotal.decimal' => 'Usa como máximo dos decimales.',
            'purchaseMaterialIds.*.exists' => 'Uno de los insumos seleccionados no existe.',
        ]);

        DB::transaction(function () use ($validated): void {
            $purchase = Purchase::create([
                'purchased_at' => $validated['purchasedAt'],
                'total_cents' => Money::fromDecimal($validated['purchaseTotal']),
                'notes' => trim($validated['purchaseNotes'] ?? '') ?: null,
            ]);

            $purchase->rawMaterials()->sync(array_map('intval', $validated['purchaseMaterialIds']));
        });

        $this->reset('purchaseTotal', 'purchaseMaterialIds', 'purchaseNotes');
        $this->purchasedAt = today()->format('Y-m-d');
        session()->flash('success', 'Compra registrada correctamente.');
    }

    public function deletePurchase(int $purchaseId): void
    {
        Purchase::findOrFail($purchaseId)->delete();

        session()->flash('success', 'Compra eliminada.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('materialsPage');
    }

    public function render()
    {
        return view('livewire.raw-materials', [
            'materials' => RawMaterial::query()
                ->when($this->search, fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15, pageName: 'materialsPage'),
            'checklistMaterials' => RawMaterial::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'purchases' => Purchase::query()
                ->with(['rawMaterials' => fn ($query) => $query->orderBy('name')])
                ->latest('purchased_at')
                ->latest('id')
                ->paginate(10, pageName: 'purchasesPage'),
            'monthSpentCents' => (int) Purchase::query()
                ->whereBetween('purchased_at', [today()->startOfMonth(), today()->endOfMonth()])
                ->sum('total_cents'),
            'editingMaterial' => $this->editMaterialId
                ? RawMaterial::find($this->editMaterialId)
                : null,
        ]);
    }
}
