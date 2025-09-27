<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->firstOrCreate([
            'email' => 'admin@orthoplex.com',
        ],[
            'name' => 'Super Admin',
            'email_verified_at' => now(),
            'password' => Hash::make('password')
        ]);
    }
}
