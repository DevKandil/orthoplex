<?php

use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create a test tenant
    $tenant = Tenant::create(['id' => 'test']);
    tenancy()->initialize($tenant);

    // Run tenant migrations
    artisan('tenants:migrate', ['--tenants' => [$tenant->id]]);

    // Seed roles and permissions
    artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
});

test('admin can list users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin, 'api')
        ->getJson('/api/users');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'created_at']
            ],
            'links',
            'meta'
        ]);
});

test('admin can create user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $userData = [
        'name' => fake()->name,
        'email' => fake()->unique()->safeEmail,
        'password' => 'password123',
        'roles' => ['member']
    ];

    $response = $this->actingAs($admin, 'api')
        ->postJson('/api/users', $userData);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'User created successfully'
        ]);

    expect($userData['email'])->toBeInDatabase('users', ['email' => $userData['email']]);
});

test('member cannot create user', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $userData = [
        'name' => fake()->name,
        'email' => fake()->unique()->safeEmail,
        'password' => 'password123'
    ];

    $response = $this->actingAs($member, 'api')
        ->postJson('/api/users', $userData);

    $response->assertStatus(403);
});

test('user can view own profile', function () {
    $user = User::factory()->create();
    $user->assignRole('member');

    $response = $this->actingAs($user, 'api')
        ->getJson("/api/users/{$user->id}");

    $response->assertStatus(200)
        ->assertJsonPath('id', $user->id);
});

test('optimistic locking prevents concurrent updates', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();

    // First update (should succeed)
    $response1 = $this->actingAs($admin, 'api')
        ->putJson("/api/users/{$user->id}", [
            'name' => 'First Update',
            'version' => $user->version
        ]);

    $response1->assertStatus(200);

    // Second update with old version (should fail)
    $response2 = $this->actingAs($admin, 'api')
        ->putJson("/api/users/{$user->id}", [
            'name' => 'Second Update',
            'version' => $user->version // Old version
        ]);

    $response2->assertStatus(409)
        ->assertJson([
            'error' => 'Conflict'
        ]);
});

test('jwt authentication works', function () {
    $user = User::factory()->create();
    $token = auth('api')->login($user);

    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
    ])->getJson('/api/auth/me');

    $response->assertStatus(200)
        ->assertJsonPath('id', $user->id);
});
