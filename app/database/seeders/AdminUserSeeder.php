<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\RoleName;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('auth.admin_email');
        $password = config('auth.admin_password');

        if (blank($email) || blank($password)) {
            return;
        }

        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => $password,
            ],
        );

        if (! $admin->hasRole(RoleName::ADMIN)) {
            $admin->assignRole(RoleName::ADMIN);
        }
    }
}
