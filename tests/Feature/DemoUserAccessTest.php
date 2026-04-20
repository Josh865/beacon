<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('demo user command creates a verified user', function () {
    $this->artisan('app:create-demo-user', [
        '--name' => 'Demo User',
        '--email' => 'demo@example.test',
        '--password' => 'demo-password',
    ])->assertSuccessful();

    $demoUser = User::query()->where('email', 'demo@example.test')->first();

    expect($demoUser)
        ->not->toBeNull()
        ->name->toBe('Demo User')
        ->email_verified_at->not->toBeNull();

    expect(password_verify('demo-password', $demoUser->password))->toBeTrue();
});
