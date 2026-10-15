<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(CashVeroGeneralSeeder::class);

        /**
         * Roles & Permissions. Runs last so the roles table exists and
         * the general seeder's own data is already in place.
         *
         * Idempotent — it creates what is missing and never revokes, so
         * re-running it cannot wipe permissions an administrator has
         * customised in the Role Management UI. Use
         * `php artisan permissions:sync --reset-roles` to deliberately
         * restore the declared defaults.
         */
        $this->call(PermissionSeeder::class);
    }
}
