<?php

namespace App\Livewire;

use App\Models\ProductionLot;
use App\Models\ProductionLotItem;
use App\Models\SaleItemLot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Ganancias')]
class Profits extends Component
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
        $lots = ProductionLot::withTrashed()
            ->latest('produced_at')
            ->get();
        $range = $this->resolveRange();

        if ($range === null) {
            return view('livewire.profits', $this->emptyReport($lots));
        }

        [$from, $to] = $range;
        $realizedAllocations = SaleItemLot::query()
            ->with([
                'saleItem.sale',
                'saleItem.productVariant.product',
                'productionLotItem.productionLot',
                'productionLotItem.productVariant.product',
            ])
            ->whereHas('saleItem.sale', fn ($query) => $query
                ->whereBetween('sold_at', [$from, $to]))
            ->when($this->lotId, fn ($query) => $query
                ->whereIn('production_lot_item_id', $this->lotItemIdsForSelectedLot()))
            ->get();

        $realizedRows = $realizedAllocations
            ->groupBy(fn (SaleItemLot $allocation): string => $allocation->productionLotItem->production_lot_id
                .'-'.$allocation->saleItem->product_variant_id)
            ->map(function (Collection $group): array {
                $first = $group->first();
                $revenueCents = $group->sum(
                    fn (SaleItemLot $allocation): int => $allocation->quantity * $allocation->saleItem->unit_price_cents,
                );
                $costCents = $group->sum(
                    fn (SaleItemLot $allocation): int => $allocation->quantity * $allocation->productionLotItem->unit_cost_cents,
                );

                return [
                    'lot' => $first->productionLotItem->productionLot,
                    'variant' => $first->saleItem->productVariant,
                    'variant_name' => $this->historicalVariantName($first),
                    'units' => $group->sum('quantity'),
                    'revenue_cents' => $revenueCents,
                    'cost_cents' => $costCents,
                    'profit_cents' => $revenueCents - $costCents,
                ];
            })
            ->sortByDesc('profit_cents')
            ->values();

        $projectedItems = ProductionLotItem::query()
            ->with(['productionLot', 'productVariant.product'])
            ->where('quantity_available', '>', 0)
            ->whereHas('productVariant', fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('active', true))
            ->whereHas('productionLot', fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('status', 'open')
                ->where(fn ($lotQuery) => $lotQuery
                    ->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', today()))
                ->whereBetween('produced_at', [$from, $to]))
            ->when($this->lotId, fn ($query) => $query->where('production_lot_id', $this->lotId))
            ->get();
        $projectedRows = $projectedItems
            ->map(function (ProductionLotItem $item): array {
                $revenueCents = $item->quantity_available * $item->productVariant->price_cents;
                $costCents = $item->quantity_available * $item->unit_cost_cents;

                return [
                    'lot' => $item->productionLot,
                    'variant' => $item->productVariant,
                    'variant_name' => $item->productVariant->name,
                    'units' => $item->quantity_available,
                    'revenue_cents' => $revenueCents,
                    'cost_cents' => $costCents,
                    'profit_cents' => $revenueCents - $costCents,
                ];
            })
            ->sortByDesc('profit_cents')
            ->values();

        return view('livewire.profits', [
            'lots' => $lots,
            'realizedRows' => $realizedRows,
            'projectedRows' => $projectedRows,
            'realizedRevenueCents' => $realizedRows->sum('revenue_cents'),
            'realizedCostCents' => $realizedRows->sum('cost_cents'),
            'realizedProfitCents' => $realizedRows->sum('profit_cents'),
            'projectedRevenueCents' => $projectedRows->sum('revenue_cents'),
            'projectedCostCents' => $projectedRows->sum('cost_cents'),
            'projectedProfitCents' => $projectedRows->sum('profit_cents'),
        ]);
    }

    /**
     * @return array<string, Collection|int>
     */
    private function emptyReport(Collection $lots): array
    {
        return [
            'lots' => $lots,
            'realizedRows' => collect(),
            'projectedRows' => collect(),
            'realizedRevenueCents' => 0,
            'realizedCostCents' => 0,
            'realizedProfitCents' => 0,
            'projectedRevenueCents' => 0,
            'projectedCostCents' => 0,
            'projectedProfitCents' => 0,
        ];
    }

    private function lotItemIdsForSelectedLot(): array
    {
        return ProductionLotItem::query()
            ->where('production_lot_id', $this->lotId)
            ->pluck('id')
            ->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function resolveRange(): ?array
    {
        $this->resetErrorBag('dateFrom');

        $from = $this->parseDate($this->dateFrom);
        $to = $this->parseDate($this->dateTo);

        if (! $from || ! $to) {
            $this->addError('dateFrom', 'Ingresa un rango de fechas válido (dd/mm/aaaa) para ver las ganancias.');

            return null;
        }

        if ($from->greaterThan($to)) {
            $this->addError('dateFrom', 'La fecha inicial no puede ser posterior a la fecha final.');

            return null;
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

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

    private function historicalVariantName(SaleItemLot $allocation): string
    {
        return $allocation->saleItem->variant_name
            ?: (string) $allocation->saleItem->productVariant?->name;
    }
}
