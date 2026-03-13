<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Pembuatan Entitas Administrator
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@system.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Injeksi Wallet Administrator (Saldo tidak terbatas untuk moderasi/testing)
        $admin->wallet()->create([
            'balance' => 999999999,
            'frozen_balance' => 0,
        ]);

        // Pembuatan Entitas Pengguna Standar
        $operative = User::create([
            'name' => 'Standard Operative',
            'email' => 'user@system.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        // Injeksi Wallet Pengguna (Saldo Rp 5.000.000 untuk pengujian bid)
        $operative->wallet()->create([
            'balance' => 5000000,
            'frozen_balance' => 0,
        ]);
    }
}
