<?php

use App\Models\User;
use Modules\EscTracker\Models\EscNotification;

/**
 * A user plus the Authorization header carrying their Sanctum token.
 */
function escSettingsActor(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('wellsync')->plainTextToken;

    return [$user, [
        'Authorization' => 'Bearer '.$token,
        'Accept' => 'application/json',
    ]];
}

test('show returns the schema defaults for a user with no settings yet', function () {
    [$user, $headers] = escSettingsActor();

    $this->getJson('/api/v1/public/esc/notifications', $headers)
        ->assertStatus(200)
        ->assertJsonPath('data.reminder_enabled', true)
        ->assertJsonPath('data.reminder_time', '20:00')
        ->assertJsonPath('data.reminder_timezone', 'Asia/Manila')
        ->assertJsonPath('data.reminder_style', 'gentle')
        ->assertJsonPath('data.last_reminded_at', null);

    // The row is materialised so the reminder scheduler can see this user.
    expect(EscNotification::where('user_id', $user->id)->count())->toBe(1);
});

test('update saves the settings', function () {
    [$user, $headers] = escSettingsActor();

    $this->putJson('/api/v1/public/esc/notifications', [
        'reminder_enabled' => false,
        'reminder_time' => '07:30',
        'reminder_timezone' => 'Asia/Singapore',
        'reminder_style' => 'firm',
    ], $headers)
        ->assertStatus(200)
        ->assertJsonPath('data.reminder_enabled', false)
        ->assertJsonPath('data.reminder_time', '07:30')
        ->assertJsonPath('data.reminder_timezone', 'Asia/Singapore')
        ->assertJsonPath('data.reminder_style', 'firm');

    $settings = EscNotification::where('user_id', $user->id)->first();

    expect($settings->reminder_enabled)->toBeFalse();
    expect($settings->reminder_timezone)->toBe('Asia/Singapore');
});

test('update accepts a full H:i:s time and normalises it', function () {
    [$user, $headers] = escSettingsActor();

    $this->putJson('/api/v1/public/esc/notifications', [
        'reminder_time' => '21:15:00',
    ], $headers)
        ->assertStatus(200)
        ->assertJsonPath('data.reminder_time', '21:15');
});

test('a partial update leaves the other fields untouched', function () {
    [$user, $headers] = escSettingsActor();

    $this->putJson('/api/v1/public/esc/notifications', [
        'reminder_time' => '06:45',
        'reminder_style' => 'firm',
    ], $headers)->assertStatus(200);

    // Only toggle `reminder_enabled` — time and style must survive.
    $this->patchJson('/api/v1/public/esc/notifications', [
        'reminder_enabled' => false,
    ], $headers)
        ->assertStatus(200)
        ->assertJsonPath('data.reminder_enabled', false)
        ->assertJsonPath('data.reminder_time', '06:45')
        ->assertJsonPath('data.reminder_style', 'firm');
});

test('update rejects a bad time, timezone, and over-long style', function () {
    [$user, $headers] = escSettingsActor();

    $this->putJson('/api/v1/public/esc/notifications', [
        'reminder_time' => '7pm',
        'reminder_timezone' => 'Mars/Olympus_Mons',
        'reminder_style' => str_repeat('a', 17),
    ], $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'reminder_time',
            'reminder_timezone',
            'reminder_style',
        ]);
});

test('last_reminded_at is not writable by the client', function () {
    [$user, $headers] = escSettingsActor();

    $this->putJson('/api/v1/public/esc/notifications', [
        'last_reminded_at' => '2026-07-22T10:00:00+00:00',
    ], $headers)
        ->assertStatus(200)
        ->assertJsonPath('data.last_reminded_at', null);

    expect(EscNotification::where('user_id', $user->id)->first()->last_reminded_at)
        ->toBeNull();
});

test('settings are scoped to the token owner', function () {
    [$user, $headers] = escSettingsActor();
    $other = User::factory()->create();
    EscNotification::create([
        'user_id' => $other->id,
        'reminder_style' => 'firm',
    ]);

    $this->putJson('/api/v1/public/esc/notifications', [
        'reminder_style' => 'gentle',
    ], $headers)->assertStatus(200);

    // The other user's row is untouched.
    expect(EscNotification::where('user_id', $other->id)->first()->reminder_style)
        ->toBe('firm');
});

test('the settings endpoints require a token', function () {
    $this->getJson('/api/v1/public/esc/notifications')->assertStatus(401);
    $this->putJson('/api/v1/public/esc/notifications', [])->assertStatus(401);
});
