<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'DIANA ARELI IBARRA OLVERA',
            'email' => 'admin@prexun.com',
            'password' => Hash::make('Em805177'),
            'role' => 'super_admin',
        ]);
    }
}
