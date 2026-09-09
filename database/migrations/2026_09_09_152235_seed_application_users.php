<?php

use Database\Seeders\UserSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the application user accounts during deployment.
     */
    public function up(): void
    {
        app(UserSeeder::class)->run();
    }

    /**
     * Preserve application accounts because deleting users during rollback is destructive.
     */
    public function down(): void {}
};
