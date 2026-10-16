<?php

namespace App\Livewire;

use App\Models\ProductionLot;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Sale;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Resumen')]
class Dashboard extends Component
{
    public function render()
    {
        $todaySales = Sale::query()->whereBetween('sold_at', [
            today()->startOfDay(),
            today()->endOfDay(),
        ]);

        return view('livewire.dashboard', [
            'salesTodayCents' => (clone $todaySales)->sum('total_cents'),
            'unitsToday' => (clone $todaySales)->withSum('items', 'quantity')->get()->sum('items_sum_quantity'),
            'finishedStock' => (int) ProductVariant::query()
                ->where('active', true)
                ->withSum('availableLotItems as available_stock', 'quantity_available')
                ->get()
                ->sum('available_stock'),
            'monthPurchasesCents' => (int) Purchase::query()
                ->whereBetween('purchased_at', [today()->startOfMonth(), today()->endOfMonth()])
                ->sum('total_cents'),
            'variants' => ProductVariant::query()
                ->where('active', true)
                ->with('product')
                ->withSum('availableLotItems as available_stock', 'quantity_available')
                ->orderBy('name')
                ->get(),
            'recentLots' => ProductionLot::query()
                ->with('items.productVariant')
                ->latest('produced_at')
                ->limit(4)
                ->get(),
            'recentSales' => Sale::query()
                ->with('items.productVariant')
                ->latest('sold_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
