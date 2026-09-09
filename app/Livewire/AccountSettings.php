<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Configuración')]
class AccountSettings extends Component
{
    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $theme = 'claro';

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->theme = in_array($user->theme, User::THEMES, true) ? $user->theme : 'claro';
    }

    public function updatePassword(): void
    {
        $validated = $this->validate([
            'currentPassword' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', Password::min(12), 'confirmed'],
        ], [
            'currentPassword.required' => 'Escribe tu contraseña actual.',
            'currentPassword.current_password' => 'La contraseña actual no es correcta.',
            'password.min' => 'La nueva contraseña debe tener al menos 12 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update(['password' => $validated['password']]);
        Auth::logoutOtherDevices($validated['password']);

        $this->reset('currentPassword', 'password', 'password_confirmation');
        session()->flash('success', 'Contraseña actualizada correctamente.');
    }

    public function updateTheme(): void
    {
        $validated = $this->validate([
            'theme' => ['required', Rule::in(User::THEMES)],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update(['theme' => $validated['theme']]);

        $this->redirectRoute('settings');
    }

    public function render(): View
    {
        return view('livewire.account-settings', [
            'themes' => User::THEME_LABELS,
        ]);
    }
}
