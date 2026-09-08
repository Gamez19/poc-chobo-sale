<?php

use App\Livewire\Dashboard;
use App\Livewire\ProductionLots;
use App\Livewire\Products;
use App\Livewire\Profits;
use App\Livewire\RawMaterials;
use App\Livewire\Reports;
use App\Livewire\Sales;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');
Route::get('/materias-primas', RawMaterials::class)->name('raw-materials');
Route::get('/productos', Products::class)->name('products');
Route::get('/lotes', ProductionLots::class)->name('production-lots');
Route::get('/ventas', Sales::class)->name('sales');
Route::get('/reportes', Reports::class)->name('reports');
Route::get('/ganancias', Profits::class)->name('profits');
