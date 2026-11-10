<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->seedProductionAdmin();
        } else {
            $this->seedDemoAccounts();
        }

        $this->call(AiProviderSeeder::class);
    }

    /** Local/testing only: well-known demo credentials must never exist in production. */
    private function seedDemoAccounts(): void
    {
        foreach ([['Admin', 'admin@example.com', 'admin'], ['Test Student', 'student@example.com', 'student']] as [$name, $email, $role]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password'), 'role' => $role],
            );
        }
    }

    /** Production: create one admin only when SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD are provided. */
    private function seedProductionAdmin(): void
    {
        $email = config('app.seed_admin.email');
        $password = config('app.seed_admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('Production: no admin created. Set SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD to create one.');

            return;
        }

        // An existing user is only promoted, never has its password overwritten.
        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->forceFill(['name' => config('app.seed_admin.name'), 'password' => Hash::make($password)]);
        }

        $user->forceFill(['role' => 'admin'])->save();
    }
}
