<?php

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use App\Notifications\VerifyEmailNotification;

beforeEach(function () {
    // Create a test tenant
    $tenant = Tenant::create(['id' => 'test']);
    tenancy()->initialize($tenant);

    // Run tenant migrations
    artisan('tenants:migrate', ['--tenants' => [$tenant->id]]);

    // Seed roles and permissions
    artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
});

test('user registration sends verification email', function () {
    Notification::fake();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123'
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'email_verification_required' => true
        ]);

    $user = User::where('email', 'test@example.com')->first();

    Notification::assertSentTo($user, VerifyEmailNotification::class);
    expect($user->email_verified_at)->toBeNull();
});

test('unverified user cannot login', function () {
    $user = User::factory()->create([
        'email_verified_at' => null
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password'
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'email_verification_required' => true
        ]);
});

test('verified user can login', function () {
    $user = User::factory()->create([
        'email_verified_at' => now()
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password'
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['access_token']);
});

test('user can verify email with valid link', function () {
    $user = User::factory()->create([
        'email_verified_at' => null
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'api.email.verify',
        now()->addMinutes(60),
        [
            'user' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]
    );

    $response = $this->get($verificationUrl);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Email verified successfully'
        ]);

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('verification link expires after time limit', function () {
    $user = User::factory()->create([
        'email_verified_at' => null
    ]);

    // Create expired verification URL
    $verificationUrl = URL::temporarySignedRoute(
        'api.email.verify',
        now()->subMinutes(5), // Expired
        [
            'user' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]
    );

    $response = $this->get($verificationUrl);

    $response->assertStatus(401); // Laravel returns 401 for expired signed URLs
});

test('user can check verification status', function () {
    $user = User::factory()->create([
        'email_verified_at' => now()
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/email/verification-status');

    $response->assertStatus(200)
        ->assertJson([
            'verified' => true
        ]);
});

test('user can resend verification email', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email_verified_at' => null
    ]);

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/email/verification-notification');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Verification email sent successfully'
        ]);

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

test('verified user cannot resend verification email', function () {
    $user = User::factory()->create([
        'email_verified_at' => now()
    ]);

    $response = $this->actingAs($user, 'api')
        ->postJson('/api/email/verification-notification');

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Email already verified'
        ]);
});

test('unverified user cannot access protected routes', function () {
    $user = User::factory()->create([
        'email_verified_at' => null
    ]);
    $user->assignRole('admin');

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/users');

    $response->assertStatus(403)
        ->assertJson([
            'verification_required' => true
        ]);
});

test('verified user can access protected routes', function () {
    $user = User::factory()->create([
        'email_verified_at' => now()
    ]);
    $user->assignRole('admin');

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/users');

    $response->assertStatus(200);
});
