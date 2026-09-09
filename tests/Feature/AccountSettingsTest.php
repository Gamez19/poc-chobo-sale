<?php

namespace Tests\Feature;

use App\Livewire\AccountSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_update_their_password(): void
    {
        $user = User::factory()->create(['password' => 'current-password']);

        Livewire::actingAs($user)
            ->test(AccountSettings::class)
            ->set('currentPassword', 'current-password')
            ->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_password_update_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'current-password']);

        Livewire::actingAs($user)
            ->test(AccountSettings::class)
            ->set('currentPassword', 'incorrect-password')
            ->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password')
            ->call('updatePassword')
            ->assertHasErrors(['currentPassword' => 'current_password']);

        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
    }

    public function test_authenticated_users_can_persist_their_theme(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AccountSettings::class)
            ->set('theme', 'oscuro')
            ->call('updateTheme')
            ->assertRedirect(route('settings'));

        $this->assertSame('oscuro', $user->fresh()->theme);
    }

    public function test_invalid_theme_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(AccountSettings::class)
            ->set('theme', 'neon')
            ->call('updateTheme')
            ->assertHasErrors(['theme' => 'in']);

        $this->assertSame('claro', $user->fresh()->theme);
    }
}
