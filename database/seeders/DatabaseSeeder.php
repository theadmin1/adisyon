<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@adisyon.com'],
            [
                'name' => 'Ahmet Yılmaz',
                'email' => 'admin@adisyon.com',
                'password' => Hash::make('password'),
            ]
        );
    }
}
