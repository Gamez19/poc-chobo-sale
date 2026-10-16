<?php

namespace App\Livewire;

use App\Models\Purchase;
use App\Models\RawMaterial;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Reportes de compras')]
class PurchaseReports extends Component
{
    public string $dateFrom;

    public string $dateTo;

    public string $rawMaterialId = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = today()->format('Y-m-d');
    }

    public function render()
    {
        $rawMaterials = RawMaterial::withTrashed()->orderBy('name')->get();
        $range = $this->resolveRange();

        if ($range === null) {
            return view('livewire.purchase-reports', [
                'rawMaterials' => $rawMaterials,
                'purchases' => collect(),
                'totalSpentCents' => 0,
                'purchasesCount' => 0,
                'averageCents' => 0,
            ]);
        }

        [$from, $to] = $range;

        $purchases = Purchase::query()
            ->with(['rawMaterials' => fn ($query) => $query->orderBy('name')])
            ->whereBetween('purchased_at', [$from, $to])
            ->when($this->rawMaterialId, fn ($query) => $query
                ->whereHas('rawMaterials', fn ($materialQuery) => $materialQuery
                    ->whereKey($this->rawMaterialId)))
            ->latest('purchased_at')
            ->latest('id')
            ->get();

        $totalSpentCents = (int) $purchases->sum('total_cents');

        return view('livewire.purchase-reports', [
            'rawMaterials' => $rawMaterials,
            'purchases' => $purchases,
            'totalSpentCents' => $totalSpentCents,
            'purchasesCount' => $purchases->count(),
            'averageCents' => $purchases->isEmpty() ? 0 : intdiv($totalSpentCents, $purchases->count()),
        ]);
    }

    /**
     * Validated day range for the report, or null when the filters are unusable.
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function resolveRange(): ?array
    {
        $this->resetErrorBag('dateFrom');

        $from = $this->parseDate($this->dateFrom);
        $to = $this->parseDate($this->dateTo);

        if (! $from || ! $to) {
            $this->addError('dateFrom', 'Ingresa un rango de fechas válido (dd/mm/aaaa) para ver el reporte.');

            return null;
        }

        if ($from->greaterThan($to)) {
            $this->addError('dateFrom', 'La fecha inicial no puede ser posterior a la fecha final.');

            return null;
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    /**
     * Parse a date input strictly, returning null for empty or malformed values.
     */
    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        if (! $date instanceof Carbon || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }
}
