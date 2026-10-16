<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Creates the first administrator. Set ADMIN_EMAIL / ADMIN_PASSWORD in .env;
     * without ADMIN_PASSWORD a random one is generated and printed once.
     * Re-running never resets an existing admin's password.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');

        $admin = User::firstOrNew(['email' => $email]);

        if (! $admin->exists) {
            $password = env('ADMIN_PASSWORD') ?: Str::password(16);
            $admin->password = $password;

            if (! env('ADMIN_PASSWORD')) {
                $this->command?->warn("Admin login: {$email} / {$password}  (shown once - change it)");
            }
        }

        $admin->forceFill(['name' => 'Administrator', 'role' => 'admin'])->save();
    }
}
