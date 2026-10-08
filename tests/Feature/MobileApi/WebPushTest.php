<?php

use App\Models\User;
use App\Models\Workspace;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Support\Facades\Http;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Modules\Creatives\Jobs\SendCreativeAssignedPush;
use Modules\Creatives\Models\Creative;
use Modules\Creatives\Models\WebPushSubscription;
use Modules\Creatives\Services\ReviewerPush;
use Modules\Creatives\Services\WebPushSender;

const WEB_ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123';

function webPushHeaders(User $user): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
}

function webSubscriptionBody(string $endpoint = WEB_ENDPOINT): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPublicKey', 'auth' => 'authSecret'], 'content_encoding' => 'aes128gcm'];
}

function webSubscribe(User $user, string $endpoint = WEB_ENDPOINT): WebPushSubscription
{
    return WebPushSubscription::create([
        'user_id' => $user->id,
        'endpoint' => $endpoint,
        'endpoint_hash' => WebPushSubscription::hashEndpoint($endpoint),
        'public_key' => 'BPublicKey',
        'auth_token' => 'authSecret',
    ]);
}

/**
 * Swaps the real Web Push client for a mock that records what was queued and
 * reports each endpoint with the given HTTP status (201 when not listed).
 */
function fakeWebPushClient(array $statuses = []): ArrayObject
{
    config(['services.webpush.public_key' => 'pub', 'services.webpush.private_key' => 'priv']);
    $sent = new ArrayObject;

    $client = Mockery::mock(WebPush::class);
    $client->shouldReceive('queueNotification')->andReturnUsing(function ($subscription, $payload, $options) use ($sent) {
        $sent[] = ['endpoint' => $subscription->getEndpoint(), 'payload' => json_decode($payload, true), 'options' => $options];
    });
    $client->shouldReceive('flush')->andReturnUsing(function () use ($sent, $statuses) {
        foreach ($sent as $message) {
            $status = $statuses[$message['endpoint']] ?? 201;
            yield new MessageSentReport(new Psr7Request('POST', $message['endpoint']), new Psr7Response($status), $status < 300, 'test');
        }
    });

    $sender = Mockery::mock(WebPushSender::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $sender->shouldReceive('client')->andReturn($client);
    app()->instance(WebPushSender::class, $sender);

    return $sent;
}

function webCreative(Workspace $workspace, array $reviewers): Creative
{
    $creative = Creative::create([
        'workspace_id' => $workspace->id,
        'creator_id' => $workspace->owner_id,
        'name' => 'UGC '.fake()->unique()->numberBetween(1, 99999),
        'creative_date' => '2026-07-01',
        'format' => 'video',
        'ads_status' => 'pending',
        'final_status' => 'for_approval',
    ]);
    $creative->assignedReviewers()->sync(collect($reviewers)->pluck('id'));

    return $creative;
}

function webWorkspaceWith(User ...$members): Workspace
{
    $workspace = Workspace::factory()->forOwner(User::factory()->withoutTwoFactor()->create())
        ->create(['creatives_module_enabled' => true]);

    foreach ($members as $member) {
        $workspace->users()->attach($member->id, ['role' => 'member']);
    }

    return $workspace;
}

test('the key endpoint returns the VAPID public key, or null when not set up', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    config(['services.webpush.public_key' => 'BPublic']);
    $this->getJson('/api/v1/creatives-tracker/web-push/key', webPushHeaders($user))->assertOk()->assertExactJson(['public_key' => 'BPublic']);

    config(['services.webpush.public_key' => null]);
    $this->getJson('/api/v1/creatives-tracker/web-push/key', webPushHeaders($user))->assertOk()->assertExactJson(['public_key' => null]);
});

test('subscribing is idempotent and moves a shared browser to the new user', function () {
    $first = User::factory()->withoutTwoFactor()->create();
    $second = User::factory()->withoutTwoFactor()->create();

    $this->postJson('/api/v1/creatives-tracker/web-push/subscription', webSubscriptionBody(), webPushHeaders($first))->assertNoContent();
    $this->postJson('/api/v1/creatives-tracker/web-push/subscription', webSubscriptionBody(), webPushHeaders($first))->assertNoContent();
    expect(WebPushSubscription::count())->toBe(1);

    $this->postJson('/api/v1/creatives-tracker/web-push/subscription', webSubscriptionBody(), webPushHeaders($second))->assertNoContent();
    expect(WebPushSubscription::count())->toBe(1)
        ->and(WebPushSubscription::first()->user_id)->toBe($second->id)
        ->and(WebPushSubscription::first()->public_key)->toBe('BPublicKey');
});

test('subscribing validates the subscription', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->postJson('/api/v1/creatives-tracker/web-push/subscription', ['endpoint' => 'http://insecure.test'], webPushHeaders($user))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
});

test('web push routes need a token', function () {
    $this->getJson('/api/v1/creatives-tracker/web-push/key')->assertUnauthorized();
    $this->postJson('/api/v1/creatives-tracker/web-push/subscription', [])->assertUnauthorized();
    $this->deleteJson('/api/v1/creatives-tracker/web-push/subscription', [])->assertUnauthorized();
});

test('unsubscribing only removes the current user\'s subscription', function () {
    $owner = User::factory()->withoutTwoFactor()->create();
    $other = User::factory()->withoutTwoFactor()->create();
    webSubscribe($owner);

    $this->deleteJson('/api/v1/creatives-tracker/web-push/subscription', ['endpoint' => WEB_ENDPOINT], webPushHeaders($other))->assertNoContent();
    expect(WebPushSubscription::count())->toBe(1);

    $this->deleteJson('/api/v1/creatives-tracker/web-push/subscription', ['endpoint' => WEB_ENDPOINT], webPushHeaders($owner))->assertNoContent();
    expect(WebPushSubscription::count())->toBe(0);
});

test('the assigned push reaches the web app as an urgent notification', function () {
    Http::fake();
    $sent = fakeWebPushClient();
    $reviewer = User::factory()->withoutTwoFactor()->create();
    webSubscribe($reviewer);
    $creative = webCreative(webWorkspaceWith($reviewer), [$reviewer]);

    (new SendCreativeAssignedPush($creative->id, [$reviewer->id]))->handle(app(ReviewerPush::class));

    expect($sent)->toHaveCount(1)
        ->and($sent[0]['endpoint'])->toBe(WEB_ENDPOINT)
        ->and($sent[0]['payload']['title'])->toBe('New creative to review')
        ->and($sent[0]['payload']['body'])->toContain($creative->name)
        ->and($sent[0]['payload']['data'])->toBe(['type' => 'creative_assigned', 'creative_id' => $creative->id])
        ->and($sent[0]['payload']['url'])->toBe('/')
        ->and($sent[0]['options']['urgency'])->toBe('high');
});

test('expired web subscriptions are deleted', function () {
    $gone = 'https://fcm.googleapis.com/fcm/send/gone';
    fakeWebPushClient([$gone => 410]);
    $reviewer = User::factory()->withoutTwoFactor()->create();
    webSubscribe($reviewer);
    webSubscribe($reviewer, $gone);

    app(WebPushSender::class)->sendToUsers([$reviewer->id], ['title' => 't', 'body' => 'b', 'data' => []]);

    expect(WebPushSubscription::pluck('endpoint')->all())->toBe([WEB_ENDPOINT]);
});

test('nothing is sent over web push until the VAPID keys are set', function () {
    config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
    $reviewer = User::factory()->withoutTwoFactor()->create();
    webSubscribe($reviewer);

    $sender = Mockery::mock(WebPushSender::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $sender->shouldNotReceive('client');

    $sender->sendToUsers([$reviewer->id], ['title' => 't', 'body' => 'b', 'data' => []]);
});

test('the daily reminder also reaches users who only have the web app', function () {
    Http::fake();
    $sent = fakeWebPushClient();
    $reviewer = User::factory()->withoutTwoFactor()->create();
    webSubscribe($reviewer);
    webCreative(webWorkspaceWith($reviewer), [$reviewer]);

    $this->travelTo(now('Asia/Manila')->setTime(9, 5));
    $this->artisan('creatives:send-review-reminders')->assertSuccessful();

    expect($sent)->toHaveCount(1)
        ->and($sent[0]['payload']['body'])->toBe('You have 1 creative waiting for your review.')
        ->and($sent[0]['options']['urgency'])->toBe('normal');
    Http::assertNothingSent();
});
