<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Totp;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('logs in with valid credentials and returns an api envelope', function (): void {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'password',
        'device_name' => 'pest',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['token', 'token_type', 'user', 'requires_two_factor'],
            'meta',
            'errors',
        ]);

    expect($response->json('data.token'))->not->toBeEmpty();
});

it('rejects invalid credentials', function (): void {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable()
        ->assertJsonPath('status', false);
});

it('rejects suspended users', function (): void {
    User::factory()->suspended()->create([
        'email' => 'suspended@example.com',
        'password' => 'password',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'suspended@example.com',
        'password' => 'password',
    ])->assertUnprocessable();
});

it('requires two factor when enabled', function (): void {
    $secret = Totp::generateSecret();

    User::factory()->create([
        'email' => '2fa@example.com',
        'password' => 'password',
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => [bcrypt('RECO-VERY1')],
        'two_factor_confirmed_at' => now(),
    ]);

    $challenge = $this->postJson('/api/v1/auth/login', [
        'email' => '2fa@example.com',
        'password' => 'password',
    ])->assertSuccessful()
        ->assertJsonPath('data.requires_two_factor', true);

    $this->postJson('/api/v1/auth/two-factor/confirm', [
        'two_factor_token' => $challenge->json('data.two_factor_token'),
        'two_factor_code' => Totp::currentCode($secret),
        'device_name' => 'pest',
    ])->assertSuccessful()
        ->assertJsonPath('data.requires_two_factor', false)
        ->assertJsonStructure(['data' => ['token', 'user']]);
});

it('logs out the current token', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('pest')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    expect($user->tokens()->count())->toBe(0);
});

it('sends a password reset link', function (): void {
    $user = User::factory()->create(['email' => 'reset@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => $user->email,
    ])->assertSuccessful();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('returns the authenticated profile', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/profile')
        ->assertSuccessful()
        ->assertJsonPath('data.email', $user->email);
});

it('changes the password', function (): void {
    $user = User::factory()->create(['password' => 'password']);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSuccessful();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('enables and confirms two factor authentication', function (): void {
    $user = User::factory()->create(['password' => 'password']);

    $setup = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/two-factor/enable')
        ->assertSuccessful();

    $secret = $setup->json('data.secret');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/two-factor/confirm-setup', [
            'code' => Totp::currentCode($secret),
        ])->assertSuccessful();

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('manages personal access tokens', function (): void {
    $user = User::factory()->create();

    $created = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/tokens', [
            'name' => 'ci-token',
            'abilities' => ['*'],
        ])->assertCreated();

    expect($created->json('data.plain_text_token'))->not->toBeEmpty();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/tokens')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $tokenId = $created->json('data.token.id');

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/tokens/{$tokenId}")
        ->assertSuccessful();
});
