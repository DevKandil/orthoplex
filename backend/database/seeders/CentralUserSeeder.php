<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CentralUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin user
        $superAdmin = User::query()->firstOrCreate([
            'email' => 'owner@orthoplex.test'
        ], [
            'name' => 'System Owner',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $ownerRole = Role::query()->where('name', 'owner')->first();
        $superAdmin->roles()->attach($ownerRole->id);

        // Create admin user
        $admin = User::query()->firstOrCreate([
            'email' => 'admin@orthoplex.test'
        ], [
            'name' => 'System Admin',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $adminRole = Role::query()->where('name', 'admin')->first();
        $admin->roles()->attach($adminRole->id);

        // Create member user
        $manager = User::query()->firstOrCreate([
            'email' => 'member@orthoplex.test'
        ], [
            'name' => 'System Manager',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $memberRole = Role::query()->where('name', 'member')->first();
        $manager->roles()->attach($memberRole->id);

        // Create auditor user
        $auditor = User::query()->firstOrCreate([
            'email' => 'auditor@orthoplex.test'
        ], [
            'name' => 'System Auditor',
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $auditorRole = Role::query()->where('name', 'auditor')->first();
        $auditor->roles()->attach($auditorRole->id);
    }
}