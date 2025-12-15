<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // SUPER ADMIN — Cannot be deleted
        User::create([
            'first_name' => 'Super',
            'last_name'  => 'Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        // Normal ADMIN
        User::create([
            'first_name' => 'Normal',
            'last_name'  => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Aesthetician
        User::create([
            'first_name' => 'Anna',
            'last_name'  => 'Aesthetician',
            'email' => 'aesthetician@example.com',
            'password' => Hash::make('password'),
            'role' => 'aesthetician',
            'email_verified_at' => now(),
        ]);

        // Client
        User::create([
            'first_name' => 'Client',
            'last_name'  => 'User',
            'email' => 'client@example.com',
            'password' => Hash::make('password'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);
    }
}
