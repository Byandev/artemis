<?php

use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;

test('login returns a sanctum token for valid credentials', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = $this->postJson('/api/v1/creatives-tracker/login', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone 15',
    ])->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email']])
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('token_type', 'Bearer');

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
    expect($user->tokens()->where('name', 'iPhone 15')->exists())->toBeTrue();
});

test('login rejects a wrong password with 401', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->postJson('/api/v1/creatives-tracker/login', ['email' => $user->email, 'password' => 'nope'])
        ->assertStatus(401)
        ->assertJsonPath('error', 'Invalid credentials.');
});

test('login rejects an unknown email with 401', function () {
    $this->postJson('/api/v1/creatives-tracker/login', ['email' => 'nobody@example.com', 'password' => 'password'])
        ->assertStatus(401);
});

test('login validates email and password', function () {
    $this->postJson('/api/v1/creatives-tracker/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

test('login asks for a 2FA code when the account has 2FA enabled', function () {
    $user = User::factory()->create(); // factory default has 2FA confirmed

    $this->postJson('/api/v1/creatives-tracker/login', ['email' => $user->email, 'password' => 'password'])
        ->assertStatus(422)
        ->assertJsonPath('two_factor_required', true);

    expect($user->tokens()->count())->toBe(0);
});

test('login accepts a valid TOTP code and rejects a wrong one', function () {
    $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
    $user = User::factory()->create(['two_factor_secret' => encrypt($secret)]);

    $this->postJson('/api/v1/creatives-tracker/login', ['email' => $user->email, 'password' => 'password', 'code' => '000000'])
        ->assertStatus(401);

    $this->postJson('/api/v1/creatives-tracker/login', [
        'email' => $user->email,
        'password' => 'password',
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertOk()->assertJsonStructure(['token']);
});

test('login accepts a recovery code once', function () {
    $user = User::factory()->create([
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-aaa', 'recovery-bbb'])),
    ]);

    $payload = ['email' => $user->email, 'password' => 'password', 'recovery_code' => 'recovery-aaa'];

    $this->postJson('/api/v1/creatives-tracker/login', $payload)->assertOk();
    $this->postJson('/api/v1/creatives-tracker/login', $payload)->assertStatus(401);
});

test('me returns the token owner and requires a token', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->getJson('/api/v1/creatives-tracker/me', ['Authorization' => 'Bearer '.$token])
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/creatives-tracker/me')->assertStatus(401);
});

test('logout revokes the current token', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->postJson('/api/v1/creatives-tracker/logout', [], ['Authorization' => 'Bearer '.$token])->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
