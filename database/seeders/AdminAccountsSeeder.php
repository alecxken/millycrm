<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Named administrator accounts with full rights (Owner role).
 * Idempotent: safe to run on every deploy; never overwrites an existing password.
 * Set DEMO_ADMIN_PASSWORD in .env on public servers.
 */
class AdminAccountsSeeder extends Seeder
{
    public const ACCOUNTS = [
        ['name' => 'Mildred Kendagor', 'email' => 'mildredkendagor@gmail.com', 'job_title' => 'Administrator', 'avatar_color' => 'violet'],
        ['name' => 'Alex Kendagor', 'email' => 'alecxkendagor@gmail.com', 'job_title' => 'Administrator', 'avatar_color' => 'indigo'],
    ];

    public function run(): void
    {
        if (\Spatie\Permission\Models\Role::where('name', Role::Owner->value)->doesntExist()) {
            $this->call(RolesAndPermissionsSeeder::class);
        }

        foreach (self::ACCOUNTS as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [...$account, 'password' => Hash::make(env('DEMO_ADMIN_PASSWORD', 'password')), 'email_verified_at' => now(), 'is_active' => true],
            );

            $user->syncRoles([Role::Owner->value]);
        }
    }
}
