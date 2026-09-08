<?php

namespace App\Livewire;

use App\Models\ProductionLot;
use App\Models\SaleItemLot;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Reportes de ventas')]
class Reports extends Component
{
    public string $dateFrom;

    public string $dateTo;

    public string $lotId = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = today()->format('Y-m-d');
    }

    public function render()
    {
        $range = $this->resolveRange();

        if ($range === null) {
            return view('livewire.reports', [
                'lots' => ProductionLot::withTrashed()->latest('produced_at')->get(),
                'rows' => collect(),
                'totalUnits' => 0,
                'totalRevenueCents' => 0,
                'salesCount' => 0,
            ]);
        }

        [$from, $to] = $range;

        $allocations = SaleItemLot::query()
            ->with([
                'saleItem.sale',
                'saleItem.productVariant.product',
                'productionLotItem.productionLot',
            ])
            ->whereHas('saleItem.sale', fn ($query) => $query
                ->whereBetween('sold_at', [$from, $to]))
            ->when($this->lotId, fn ($query) => $query
                ->whereHas('productionLotItem', fn ($lotItemQuery) => $lotItemQuery
                    ->where('production_lot_id', $this->lotId)))
            ->get();

        $rows = $allocations
            ->groupBy(fn ($allocation) => $allocation->productionLotItem->production_lot_id
                .'-'.$allocation->saleItem->product_variant_id
                .'-'.$this->historicalVariantName($allocation))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'lot' => $first->productionLotItem->productionLot,
                    'variant' => $first->saleItem->productVariant,
                    'variant_name' => $this->historicalVariantName($first),
                    'units' => $group->sum('quantity'),
                    'revenue_cents' => $group->sum(
                        fn ($allocation) => $allocation->quantity * $allocation->saleItem->unit_price_cents
                    ),
                ];
            })
            ->sortByDesc(fn ($row) => $row['revenue_cents'])
            ->values();

        return view('livewire.reports', [
            'lots' => ProductionLot::withTrashed()->latest('produced_at')->get(),
            'rows' => $rows,
            'totalUnits' => $rows->sum('units'),
            'totalRevenueCents' => $rows->sum('revenue_cents'),
            'salesCount' => $allocations->pluck('saleItem.sale_id')->unique()->count(),
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

    /**
     * Name the variant had when the sale happened, falling back to the current
     * relation name for rows recorded before the snapshot existed.
     */
    private function historicalVariantName(SaleItemLot $allocation): string
    {
        return $allocation->saleItem->variant_name
            ?: (string) $allocation->saleItem->productVariant?->name;
    }
}
