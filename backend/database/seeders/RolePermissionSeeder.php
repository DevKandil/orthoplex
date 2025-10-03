<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Delete all roles and permissions with their relationships
        Role::query()->delete();
        Permission::query()->delete();

        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users' => ['read', 'create', 'update', 'delete', 'invite', 'restore'],
            'analytics' => ['read'],
            'system' => ['manage'],
            'tenants' => ['manage'],
            'api' => ['manage'],
            'webhooks' => ['manage'],
            'gdpr' => ['manage']
        ];

        // Create roles and assign permissions
        $rolesStructure = [
            'owner' => $permissions, // All permissions
            'admin' => [
                'users' => ['read', 'create', 'update', 'delete', 'invite'],
                'analytics' => ['read'],
                'api' => ['manage'],
                'webhooks' => ['manage'],
            ],
            'member' => [
                'users' => ['read'],
            ],
            'auditor' => [
                'users' => ['read'],
                'analytics' => ['read'],
            ],
        ];

        // Generate all unique permissions from the permissions structure
        $allPermissions = [];
        foreach ($permissions as $resource => $actions) {
            foreach ($actions as $action) {
                $permissionName = $resource . '.' . $action;
                $allPermissions[$permissionName] = $permissionName;
            }
        }

        // Create all permissions
        Permission::upsert(
            collect($allPermissions)->map(function($permissionName) {
                return [
                    'name' => $permissionName,
                    'guard_name' => 'api'
                ];
            })->toArray(),
            ['name', 'guard_name']
        );

        $this->command->info('Created ' . count($allPermissions) . ' permissions');

        // Create roles and assign permissions
        foreach ($rolesStructure as $roleName => $rolePermissions) {
            // Create role if it doesn't exist
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'api'
            ]);

            // Generate permissions for this role
            $permissionsToAssign = [];
            foreach ($rolePermissions as $resource => $actions) {
                foreach ($actions as $action) {
                    $permissionName = $resource . '.' . $action;
                    $permissionsToAssign[] = $permissionName;
                }
            }

            // Sync permissions to role
            $role->syncPermissions($permissionsToAssign);

            $this->command->info("Role '{$roleName}' created/updated with " . count($permissionsToAssign) . " permissions");
        }

        $this->command->info('Roles and Permissions seeded successfully!');
    }
}