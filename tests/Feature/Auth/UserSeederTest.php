<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PASSWORD = 'seeded-password-for-tests';

    protected function tearDown(): void
    {
        $this->forgetSeedPassword();

        parent::tearDown();
    }

    public function test_it_fails_when_the_seed_password_is_missing(): void
    {
        $this->forgetSeedPassword();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SEED_USER_PASSWORD is required');

        $this->seed(UserSeeder::class);
    }

    public function test_it_fails_when_the_seed_password_is_too_short(): void
    {
        $this->setSeedPassword('short-pass');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('at least 12 characters');

        $this->seed(UserSeeder::class);
    }

    public function test_it_seeds_the_two_application_users(): void
    {
        $this->setSeedPassword(self::TEST_PASSWORD);

        $this->seed(UserSeeder::class);

        $this->assertSame(2, User::count());

        $keyling = User::where('email', 'makent3@gmail.com')->firstOrFail();
        $edwin = User::where('email', 'edwingamez19@gmail.com')->firstOrFail();

        $this->assertSame('Keyling', $keyling->name);
        $this->assertSame('Edwin', $edwin->name);
        $this->assertNotNull($keyling->email_verified_at);
        $this->assertNotNull($edwin->email_verified_at);
    }

    public function test_it_hashes_the_seeded_password(): void
    {
        $this->setSeedPassword(self::TEST_PASSWORD);

        $this->seed(UserSeeder::class);

        $keyling = User::where('email', 'makent3@gmail.com')->firstOrFail();

        $this->assertNotSame(self::TEST_PASSWORD, $keyling->password);
        $this->assertTrue(Hash::check(self::TEST_PASSWORD, $keyling->password));
    }

    public function test_it_is_idempotent_across_runs(): void
    {
        $this->setSeedPassword(self::TEST_PASSWORD);

        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(2, User::count());
    }

    private function setSeedPassword(string $password): void
    {
        $_ENV['SEED_USER_PASSWORD'] = $password;
        $_SERVER['SEED_USER_PASSWORD'] = $password;
        putenv('SEED_USER_PASSWORD='.$password);
    }

    private function forgetSeedPassword(): void
    {
        unset($_ENV['SEED_USER_PASSWORD'], $_SERVER['SEED_USER_PASSWORD']);
        putenv('SEED_USER_PASSWORD');
    }
}
