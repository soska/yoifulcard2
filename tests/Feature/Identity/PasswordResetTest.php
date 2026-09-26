<?php

use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('password reset routes are not available', function () {
    expect(Features::enabled(Features::resetPasswords()))->toBeFalse();

    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password', ['email' => 'ana@example.com'])->assertNotFound();
    $this->get('/reset-password/some-token')->assertNotFound();
    $this->post('/reset-password', [
        'token' => 'some-token',
        'email' => 'ana@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertNotFound();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->missing('canResetPassword'));
});
