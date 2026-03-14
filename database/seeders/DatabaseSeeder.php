<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@system.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $admin->wallet()->create([
            'balance' => 999999999,
            'frozen_balance' => 0,
        ]);

        $operative = User::create([
            'name' => 'Standard Operative',
            'email' => 'user@system.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        $operative->wallet()->create([
            'balance' => 5000000,
            'frozen_balance' => 0,
        ]);

        $categories = [
            ['name' => 'Automotive', 'slug' => 'automotive'],
            ['name' => 'Real Estate', 'slug' => 'real-estate'],
            ['name' => 'Electronics', 'slug' => 'electronics'],
            ['name' => 'Collectibles & Art', 'slug' => 'collectibles-art'],
            ['name' => 'Fashion & Jewelry', 'slug' => 'fashion-jewelry'],
            ['name' => 'Miscellaneous', 'slug' => 'miscellaneous'],
        ];

        foreach ($categories as $category) {
            \App\Models\Category::create($category);
        }
    }
}
