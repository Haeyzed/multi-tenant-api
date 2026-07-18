<?php

declare(strict_types=1);

use App\Services\Central\Auth\PasswordResetService;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

it('changes password when current password matches', function (): void {
    $user = User::factory()->create(['password' => 'password']);
    $service = app(PasswordResetService::class);

    $service->changePassword($user, 'password', 'updated-password-99');

    expect(Hash::check('updated-password-99', $user->fresh()->password))->toBeTrue();
});

it('rejects incorrect current password', function (): void {
    $user = User::factory()->create(['password' => 'password']);
    $service = app(PasswordResetService::class);

    $service->changePassword($user, 'wrong', 'updated-password-99');
})->throws(ValidationException::class);
