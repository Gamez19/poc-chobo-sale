<?php

namespace App\Livewire;

use App\Models\ProductionLotItem;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
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

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = today()->format('Y-m-d');
    }

    public function render()
    {
        $potentialRows = $this->potentialRows();
        $range = $this->resolveRange();

        if ($range === null) {
            return view('livewire.profits', $this->emptyReport($potentialRows));
        }

        [$from, $to] = $range;
        $salesCents = (int) Sale::query()
            ->whereBetween('sold_at', [$from, $to])
            ->sum('total_cents');
        $purchases = Purchase::query()
            ->with(['rawMaterials' => fn ($query) => $query->orderBy('name')])
            ->whereBetween('purchased_at', [$from, $to])
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->get();
        $purchasesCents = (int) $purchases->sum('total_cents');
        $profitCents = $salesCents - $purchasesCents;

        return view('livewire.profits', [
            'salesRows' => $this->salesRows($from, $to),
            'purchases' => $purchases,
            'potentialRows' => $potentialRows,
            'salesCents' => $salesCents,
            'purchasesCents' => $purchasesCents,
            'profitCents' => $profitCents,
            'marginPercent' => $this->marginPercent($profitCents, $salesCents),
            'potentialRevenueCents' => (int) $potentialRows->sum('revenue_cents'),
        ]);
    }

    /**
     * Units and revenue sold in the range, grouped by the variant name recorded at sale time.
     *
     * @return Collection<int, array{variant_name: string, units: int, revenue_cents: int}>
     */
    private function salesRows(Carbon $from, Carbon $to): Collection
    {
        return SaleItem::query()
            ->whereHas('sale', fn ($query) => $query->whereBetween('sold_at', [$from, $to]))
            ->selectRaw('variant_name, sum(quantity) as units, sum(subtotal_cents) as revenue_cents')
            ->groupBy('variant_name')
            ->orderByDesc('revenue_cents')
            ->get()
            ->map(fn (SaleItem $item): array => [
                'variant_name' => (string) $item->variant_name,
                'units' => (int) $item->units,
                'revenue_cents' => (int) $item->revenue_cents,
            ]);
    }

    /**
     * Available finished stock valued at the current variant price (revenue only).
     *
     * @return Collection<int, array{variant_name: string, units: int, revenue_cents: int}>
     */
    private function potentialRows(): Collection
    {
        return ProductionLotItem::query()
            ->with('productVariant')
            ->where('quantity_available', '>', 0)
            ->whereHas('productVariant', fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('active', true))
            ->whereHas('productionLot', fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('status', 'open')
                ->where(fn ($lotQuery) => $lotQuery
                    ->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', today())))
            ->get()
            ->groupBy('product_variant_id')
            ->map(function (Collection $items): array {
                $variant = $items->first()->productVariant;
                $units = (int) $items->sum('quantity_available');

                return [
                    'variant_name' => $variant->name,
                    'units' => $units,
                    'revenue_cents' => $units * $variant->price_cents,
                ];
            })
            ->sortByDesc('revenue_cents')
            ->values();
    }

    /**
     * Profit as a percentage of sales revenue, zero-safe.
     */
    private function marginPercent(int $profitCents, int $revenueCents): string
    {
        if ($revenueCents === 0) {
            return number_format(0, 1);
        }

        return number_format($profitCents / $revenueCents * 100, 1);
    }

    /**
     * @return array<string, Collection|int|string>
     */
    private function emptyReport(Collection $potentialRows): array
    {
        return [
            'salesRows' => collect(),
            'purchases' => collect(),
            'potentialRows' => $potentialRows,
            'salesCents' => 0,
            'purchasesCents' => 0,
            'profitCents' => 0,
            'marginPercent' => $this->marginPercent(0, 0),
            'potentialRevenueCents' => (int) $potentialRows->sum('revenue_cents'),
        ];
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
}
