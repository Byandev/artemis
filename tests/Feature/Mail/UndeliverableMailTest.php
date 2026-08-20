<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * What a user sees when Brevo won't take the message — an unauthorised sending
 * IP being the usual cause. None of these flows may answer with a 500, and none
 * of them may repeat Brevo's own words back to the person waiting.
 */
const BREVO_API = 'https://api.brevo.com/v3/smtp/email';

/** Point the app at Brevo and have it refuse the way an unlisted IP is refused. */
function brevoRefusesTheIp(): void
{
    config([
        'mail.default' => 'brevo',
        'services.brevo.key' => 'test-key',
        'services.brevo.endpoint' => BREVO_API,
    ]);

    Http::fake([BREVO_API => Http::response([
        'code' => 'unauthorized',
        'message' => 'Unrecognised IP address 203.0.113.7. Please add it in your authorised IPs',
    ], 401)]);
}

test('a reset link that cannot be sent comes back as advice, not a crash', function () {
    brevoRefusesTheIp();

    $user = User::factory()->create();

    $response = $this->from(route('password.request'))
        ->post(route('password.email'), ['email' => $user->email]);

    $response->assertRedirect(route('password.request'));
    $response->assertSessionHasErrors('email');

    $error = session('errors')->first('email');

    expect($error)->toContain('try again')
        // Nothing of Brevo's reply, and above all not the server's address.
        ->and($error)->not->toContain('Brevo')
        ->and($error)->not->toContain('203.0.113.7');
});

test('a verification email that cannot be sent leaves the user on the page with a reason', function () {
    brevoRefusesTheIp();

    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.send'));

    $response->assertRedirect(route('verification.notice'));
    $response->assertSessionHasErrors('email');
    $response->assertSessionMissing('status');
});

test('registration survives a verification email that never leaves', function () {
    brevoRefusesTheIp();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms_accepted' => true,
    ]);

    // The account is the point of the form; the email is a follow-up. Failing
    // the second must not throw away the first.
    $this->assertAuthenticated();
    $response->assertRedirect(route('workspaces.setup', absolute: false));
    expect(User::where('email', 'new@example.com')->exists())->toBeTrue();
});
