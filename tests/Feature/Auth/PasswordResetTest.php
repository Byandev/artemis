<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $response = $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('the reset email carries our wording and a working link', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $mail = $notification->toMail($user);

        expect($mail->subject)->toBe('Reset your '.config('app.name').' password')
            ->and($mail->actionText)->toBe('Reset password')
            ->and($mail->actionUrl)->toContain(route('password.reset', $notification->token))
            // The expiry is the one thing a reader acts on, so it has to be there.
            ->and($mail->introLines[0] ?? '')->toContain('reset the password')
            ->and(implode(' ', $mail->outroLines))->toContain('60 minutes');

        return true;
    });
});

test('requesting reset links is throttled per caller', function () {
    Notification::fake();

    $user = User::factory()->create();

    // The seventh in a minute is the one over the limit.
    foreach (range(1, 6) as $ignored) {
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();
    }

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertStatus(429);
});

test('an unknown email is answered the same way as a known one', function () {
    Notification::fake();

    // Anything else turns the form into a way to test whether an account exists.
    $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('password.request'));

    Notification::assertNothingSent();
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.store'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});
