<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\Creatives\Jobs\SendCreativeAssignedPush;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\CreativeReview;
use Modules\Creatives\Models\PushToken;
use Modules\Creatives\Models\ReminderSetting;
use Modules\Creatives\Services\ReviewerPush;

const EXPO_SEND_URL = 'https://exp.host/--/api/v2/push/send';

function pushWorkspace(): Workspace
{
    $owner = User::factory()->withoutTwoFactor()->create();

    return Workspace::factory()->forOwner($owner)->create(['creatives_module_enabled' => true]);
}

function pushReviewer(Workspace $workspace, ?string $token = null): User
{
    $user = User::factory()->withoutTwoFactor()->create();
    $workspace->users()->attach($user->id, ['role' => 'member']);

    if ($token) {
        PushToken::create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android']);
    }

    return $user;
}

function pushCreative(Workspace $workspace, array $overrides = []): Creative
{
    return Creative::create(array_merge([
        'workspace_id' => $workspace->id,
        'creator_id' => $workspace->owner_id,
        'name' => 'Creative '.fake()->unique()->numberBetween(1, 99999),
        'creative_date' => '2026-07-01',
        'format' => 'video',
        'ads_status' => 'pending',
        'final_status' => 'for_approval',
    ], $overrides));
}

function pushHeaders(User $user): array
{
    // The test app keeps the resolved Sanctum user between requests; forget
    // it so a test can switch users.
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
}

function fakeExpo(array $tickets = []): void
{
    Http::fake([EXPO_SEND_URL => Http::response(['data' => $tickets])]);
}

test('registering a push token is idempotent', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $body = ['token' => 'ExponentPushToken[abc]', 'platform' => 'android', 'device_name' => 'Pixel'];

    $this->postJson('/api/v1/creatives-tracker/push-token', $body, pushHeaders($user))->assertNoContent();
    $this->postJson('/api/v1/creatives-tracker/push-token', $body, pushHeaders($user))->assertNoContent();

    expect(PushToken::count())->toBe(1)
        ->and(PushToken::first()->user_id)->toBe($user->id)
        ->and(PushToken::first()->device_name)->toBe('Pixel');
});

test('a token registered by another user moves to them', function () {
    $first = User::factory()->withoutTwoFactor()->create();
    $second = User::factory()->withoutTwoFactor()->create();
    $body = ['token' => 'ExponentPushToken[shared]', 'platform' => 'ios'];

    $this->postJson('/api/v1/creatives-tracker/push-token', $body, pushHeaders($first))->assertNoContent();
    $this->postJson('/api/v1/creatives-tracker/push-token', $body, pushHeaders($second))->assertNoContent();

    expect(PushToken::count())->toBe(1)->and(PushToken::first()->user_id)->toBe($second->id);
});

test('registering validates the token and platform', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->postJson('/api/v1/creatives-tracker/push-token', ['token' => 'nope', 'platform' => 'web'], pushHeaders($user))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token', 'platform']);
});

test('push token routes need a token', function () {
    $this->postJson('/api/v1/creatives-tracker/push-token', [])->assertUnauthorized();
    $this->deleteJson('/api/v1/creatives-tracker/push-token', [])->assertUnauthorized();
});

test('deleting only removes the current user\'s token', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    PushToken::create(['user_id' => $owner->id, 'token' => 'ExponentPushToken[mine]', 'platform' => 'android']);

    $this->deleteJson('/api/v1/creatives-tracker/push-token', ['token' => 'ExponentPushToken[mine]'], pushHeaders($other))
        ->assertNoContent();
    expect(PushToken::count())->toBe(1);

    $this->deleteJson('/api/v1/creatives-tracker/push-token', ['token' => 'ExponentPushToken[mine]'], pushHeaders($owner))
        ->assertNoContent();
    expect(PushToken::count())->toBe(0);

    // Already gone is still a success.
    $this->deleteJson('/api/v1/creatives-tracker/push-token', ['token' => 'ExponentPushToken[mine]'], pushHeaders($owner))
        ->assertNoContent();
});

test('assigning reviewers queues a push only for the newly attached ones', function () {
    Queue::fake();
    $workspace = pushWorkspace();
    $existing = pushReviewer($workspace);
    $added = pushReviewer($workspace);
    $creative = pushCreative($workspace);
    $creative->assignedReviewers()->sync([$existing->id]);

    SendCreativeAssignedPush::forSync($creative, $creative->assignedReviewers()->sync([$existing->id, $added->id]));

    Queue::assertPushedOn('push', SendCreativeAssignedPush::class, fn ($job) => $job->userIds === [$added->id]);
});

test('no push is queued for a creative that is not for approval', function () {
    Queue::fake();
    $workspace = pushWorkspace();
    $reviewer = pushReviewer($workspace);
    $creative = pushCreative($workspace, ['final_status' => 'approved']);

    SendCreativeAssignedPush::forSync($creative, $creative->assignedReviewers()->sync([$reviewer->id]));

    Queue::assertNothingPushed();
});

test('the assigned push goes to every token of the reviewer', function () {
    fakeExpo([['status' => 'ok', 'id' => '1'], ['status' => 'ok', 'id' => '2']]);
    $workspace = pushWorkspace();
    $reviewer = pushReviewer($workspace, 'ExponentPushToken[phone]');
    PushToken::create(['user_id' => $reviewer->id, 'token' => 'ExponentPushToken[tablet]', 'platform' => 'ios']);
    $creative = pushCreative($workspace, ['name' => 'UGC 01']);
    $creative->assignedReviewers()->sync([$reviewer->id]);

    (new SendCreativeAssignedPush($creative->id, [$reviewer->id]))->handle(app(ReviewerPush::class));

    Http::assertSent(function (Request $request) use ($creative) {
        $messages = $request->data();

        return $request->url() === EXPO_SEND_URL
            && collect($messages)->pluck('to')->sort()->values()->all() === ['ExponentPushToken[phone]', 'ExponentPushToken[tablet]']
            && $messages[0]['title'] === 'New creative to review'
            && str_contains($messages[0]['body'], 'UGC 01')
            && $messages[0]['priority'] === 'high'
            && $messages[0]['channelId'] === 'reviews'
            && $messages[0]['data'] === ['type' => 'creative_assigned', 'creative_id' => $creative->id];
    });
});

test('the assigned push is skipped once the reviewer is unassigned', function () {
    fakeExpo();
    $workspace = pushWorkspace();
    $reviewer = pushReviewer($workspace, 'ExponentPushToken[phone]');
    $creative = pushCreative($workspace);

    (new SendCreativeAssignedPush($creative->id, [$reviewer->id]))->handle(app(ReviewerPush::class));

    Http::assertNothingSent();
});

test('tokens Expo reports as not registered are deleted', function () {
    fakeExpo([['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']]]);
    $workspace = pushWorkspace();
    $reviewer = pushReviewer($workspace, 'ExponentPushToken[dead]');
    $creative = pushCreative($workspace);
    $creative->assignedReviewers()->sync([$reviewer->id]);

    (new SendCreativeAssignedPush($creative->id, [$reviewer->id]))->handle(app(ReviewerPush::class));

    expect(PushToken::count())->toBe(0);
});

/** Freezes the clock at the given Asia/Manila time today. */
function atManilaTime(string $time): void
{
    test()->travelTo(now(ReminderSetting::TIMEZONE)->setTimeFromTimeString($time));
}

test('the daily reminder counts pending creatives and skips users with none', function () {
    fakeExpo();
    atManilaTime('09:00');
    $workspace = pushWorkspace();
    $busy = pushReviewer($workspace, 'ExponentPushToken[busy]');
    $idle = pushReviewer($workspace, 'ExponentPushToken[idle]');

    foreach (range(1, 2) as $_) {
        pushCreative($workspace)->assignedReviewers()->sync([$busy->id, $idle->id]);
    }
    $reviewed = pushCreative($workspace);
    $reviewed->assignedReviewers()->sync([$busy->id, $idle->id]);
    CreativeReview::create(['creative_id' => $reviewed->id, 'reviewer_id' => $busy->id, 'status' => 'approved']);

    // idle has reviewed everything else too.
    Creative::all()->each(fn ($c) => CreativeReview::firstOrCreate(['creative_id' => $c->id, 'reviewer_id' => $idle->id], ['status' => 'approved']));

    $this->artisan('creatives:send-review-reminders')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => count($request->data()) === 1
        && $request->data()[0]['to'] === 'ExponentPushToken[busy]'
        && $request->data()[0]['body'] === 'You have 2 creatives waiting for your review.'
        && $request->data()[0]['data'] === ['type' => 'pending_reminder', 'count' => 2]);
});

test('reminder settings default to on at 9:00 Manila time', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->getJson('/api/v1/creatives-tracker/reminder-settings', pushHeaders($user))
        ->assertOk()
        ->assertExactJson(['daily_reminder' => ['enabled' => true, 'time' => '09:00', 'timezone' => 'Asia/Manila']]);

    expect(ReminderSetting::count())->toBe(0);
});

test('a user can change their reminder time and turn it off', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->putJson('/api/v1/creatives-tracker/reminder-settings', ['enabled' => false, 'time' => '17:30'], pushHeaders($user))
        ->assertOk()
        ->assertExactJson(['daily_reminder' => ['enabled' => false, 'time' => '17:30', 'timezone' => 'Asia/Manila']]);

    $this->getJson('/api/v1/creatives-tracker/reminder-settings', pushHeaders($user))
        ->assertJsonPath('daily_reminder.enabled', false)
        ->assertJsonPath('daily_reminder.time', '17:30');

    expect(ReminderSetting::where('user_id', $user->id)->count())->toBe(1);
});

test('reminder settings reject an invalid time', function (array $body) {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->putJson('/api/v1/creatives-tracker/reminder-settings', $body, pushHeaders($user))
        ->assertUnprocessable();
})->with([
    'not a time' => [['enabled' => true, 'time' => 'morning']],
    'out of range' => [['enabled' => true, 'time' => '25:00']],
    'missing enabled' => [['time' => '09:00']],
]);

test('reminder settings require a token', function () {
    $this->getJson('/api/v1/creatives-tracker/reminder-settings')->assertUnauthorized();
    $this->putJson('/api/v1/creatives-tracker/reminder-settings', ['enabled' => true, 'time' => '09:00'])->assertUnauthorized();
});

test('the daily reminder goes out at each user\'s own time, once a day', function () {
    fakeExpo();
    $workspace = pushWorkspace();
    $evening = pushReviewer($workspace, 'ExponentPushToken[evening]');
    $morning = pushReviewer($workspace, 'ExponentPushToken[morning]');
    pushCreative($workspace)->assignedReviewers()->sync([$evening->id, $morning->id]);
    ReminderSetting::create(['user_id' => $evening->id, 'daily_reminder_time' => '18:00:00']);

    $sentTo = function () {
        $tokens = [];
        Http::recorded(function (Request $request) use (&$tokens) {
            foreach ($request->data() as $message) {
                $tokens[] = $message['to'];
            }
        });

        return $tokens;
    };

    // 9:00 is the default, so only the morning user is due.
    atManilaTime('09:00');
    $this->artisan('creatives:send-review-reminders')->assertSuccessful();
    expect($sentTo())->toBe(['ExponentPushToken[morning]']);

    // A later run in the grace window doesn't send it again.
    atManilaTime('09:10');
    $this->artisan('creatives:send-review-reminders')->assertSuccessful();
    expect($sentTo())->toBe(['ExponentPushToken[morning]']);

    // Not yet 18:00, then a scheduler run a few minutes late still catches it.
    atManilaTime('17:59');
    $this->artisan('creatives:send-review-reminders')->assertSuccessful();
    atManilaTime('18:05');
    $this->artisan('creatives:send-review-reminders')->assertSuccessful();
    expect($sentTo())->toBe(['ExponentPushToken[morning]', 'ExponentPushToken[evening]']);
});

test('the daily reminder is skipped for users who turned it off', function () {
    fakeExpo();
    $workspace = pushWorkspace();
    $user = pushReviewer($workspace, 'ExponentPushToken[off]');
    pushCreative($workspace)->assignedReviewers()->sync([$user->id]);
    ReminderSetting::create(['user_id' => $user->id, 'daily_reminder_enabled' => false]);

    atManilaTime('09:00');
    $this->artisan('creatives:send-review-reminders')->assertSuccessful();

    Http::assertNothingSent();
});
