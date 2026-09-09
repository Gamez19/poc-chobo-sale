<?php

use App\Livewire\Actions\Logout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| Public registration is intentionally disabled: accounts are provisioned by
| the database seeder. Email verification routes are intentionally omitted
| because access is not gated on email verification.
|
*/

Route::middleware('guest')->group(function () {
    Volt::route('login', 'pages.auth.login')
        ->name('login');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', function (Logout $logout): RedirectResponse {
        $logout();

        return redirect()->route('login');
    })->name('logout');

    Volt::route('confirm-password', 'pages.auth.confirm-password')
        ->name('password.confirm');
});
