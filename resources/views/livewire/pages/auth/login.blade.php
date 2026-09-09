<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="login-card">
    <div class="login-card-header">
        <p class="login-kicker">Bienvenido de vuelta</p>
        <h2>Ingresá a tu cuenta</h2>
        <p>Continuá con tu trabajo y mantené cada detalle de tu operación bajo control.</p>
    </div>

    <x-auth-session-status class="login-status" :status="session('status')" />

    <form class="login-form" wire:submit="login">
        <div class="login-field">
            <label class="login-label" for="email">Correo electrónico</label>
            <input wire:model="form.email" id="email" class="login-input" type="email" name="email" required autofocus autocomplete="username" placeholder="vos@ejemplo.com">
            <x-input-error class="login-error" :messages="$errors->get('form.email')" />
        </div>

        <div class="login-field">
            <label class="login-label" for="password">Contraseña</label>
            <input wire:model="form.password" id="password" class="login-input" type="password" name="password" required autocomplete="current-password" placeholder="Ingresá tu contraseña">
            <x-input-error class="login-error" :messages="$errors->get('form.password')" />
        </div>

        <div class="login-options">
            <label class="login-check" for="remember">
                <input wire:model="form.remember" id="remember" type="checkbox" name="remember">
                <span>Recordarme en este dispositivo</span>
            </label>

            @if (Route::has('password.request'))
                <a class="login-link" href="{{ route('password.request') }}" wire:navigate>¿Olvidaste tu contraseña?</a>
            @endif
        </div>

        <button class="login-submit" type="submit" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">Ingresar</span>
            <span wire:loading wire:target="login">Ingresando…</span>
        </button>
    </form>
</div>
