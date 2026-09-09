<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Minimum length accepted for the seeded account password.
     */
    private const MINIMUM_PASSWORD_LENGTH = 12;

    /**
     * Seed the application accounts.
     *
     * Accounts are provisioned here because public registration is disabled.
     */
    public function run(): void
    {
        $password = $this->resolvePassword();

        /** @var array<int, array{name: string, email: string}> $accounts */
        $accounts = [
            ['name' => 'Keyling', 'email' => 'makent3@gmail.com'],
            ['name' => 'Edwin', 'email' => 'edwingamez19@gmail.com'],
        ];

        foreach ($accounts as $account) {
            $user = User::firstOrNew(['email' => $account['email']]);

            $user->forceFill([
                'name' => $account['name'],
                'email' => $account['email'],
                'password' => $password,
                'email_verified_at' => now(),
            ])->save();
        }
    }

    /**
     * Read and validate the required seed password from the environment.
     */
    private function resolvePassword(): string
    {
        $password = env('SEED_USER_PASSWORD');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException(
                'SEED_USER_PASSWORD is required to seed application users. Set it in your environment before running the seeder.'
            );
        }

        if (mb_strlen($password) < self::MINIMUM_PASSWORD_LENGTH) {
            throw new RuntimeException(
                'SEED_USER_PASSWORD must be at least '.self::MINIMUM_PASSWORD_LENGTH.' characters long.'
            );
        }

        return $password;
    }
}
