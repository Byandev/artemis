<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\EscTracker\Models\DailyEscRecord;

/**
 * A user plus the Authorization header carrying their Sanctum token.
 */
function escActor(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('wellsync')->plainTextToken;

    return [$user, [
        'Authorization' => 'Bearer '.$token,
        // Without this, validation failures redirect (302) instead of
        // returning a 422 body — same as any real client must do.
        'Accept' => 'application/json',
    ]];
}

test('store creates a record on first submit of the day', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'record_date' => '2026-07-20',
        'learning_text' => 'Read a chapter on systems design.',
        'movement_text' => 'Ran 5km.',
        'meditation_completed' => true,
    ], $headers)
        ->assertStatus(201)
        ->assertJsonPath('created', true)
        ->assertJsonPath('data.user_id', $user->id);

    expect(DailyEscRecord::where('user_id', $user->id)
        ->where('record_date', '2026-07-20')
        ->count())->toBe(1);
});

test('store upserts on resubmit for the same day', function () {
    [$user, $headers] = escActor();

    $payload = [
        'record_date' => '2026-07-20',
        'learning_text' => 'First pass.',
        'meditation_completed' => false,
    ];

    $this->postJson('/api/v1/public/esc/daily-records', $payload, $headers)
        ->assertStatus(201);

    $payload['learning_text'] = 'Revised.';

    $this->postJson('/api/v1/public/esc/daily-records', $payload, $headers)
        ->assertStatus(200)
        ->assertJsonPath('created', false);

    expect(DailyEscRecord::count())->toBe(1);
    expect(DailyEscRecord::first()->learning_text)->toBe('Revised.');
});

test('the record always belongs to the token owner', function () {
    [$owner, $headers] = escActor();
    $other = User::factory()->create();

    // Even if the caller tries to name someone else, there is no email field to
    // honour — the record is written against the token owner.
    $this->postJson('/api/v1/public/esc/daily-records', [
        'email' => $other->email,
        'user_id' => $other->id,
        'meditation_completed' => true,
    ], $headers)->assertStatus(201);

    expect(DailyEscRecord::first()->user_id)->toBe($owner->id);
    expect(DailyEscRecord::where('user_id', $other->id)->count())->toBe(0);
});

test('record_date defaults to today when omitted', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'meditation_completed' => true,
    ], $headers)->assertStatus(201);

    expect(DailyEscRecord::first()->record_date->toDateString())
        ->toBe(now()->toDateString());
});

test('a partial submission is accepted with the unlogged pillars left null', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'learning_text' => 'Logged learning only.',
        'meditation_completed' => true,
    ], $headers)
        ->assertStatus(201)
        ->assertJsonPath('data.movement_text', null);
});

test('an uploaded movement image comes back as a renderable url', function () {
    Storage::fake('public');

    [$user, $headers] = escActor();

    $response = $this->post('/api/v1/public/esc/daily-records', [
        'movement_text' => 'Gym.',
        'movement_image' => UploadedFile::fake()->image('run.jpg'),
        'meditation_completed' => true,
    ], $headers)->assertStatus(201);

    $url = $response->json('data.movement_image_url');

    expect($url)->toStartWith('/storage/');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $url));
});

test('store persists a meditation_url and echoes it back', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'meditation_completed' => true,
        'meditation_url' => 'https://www.headspace.com/play/12345',
    ], $headers)
        ->assertStatus(201)
        ->assertJsonPath('data.meditation_url', 'https://www.headspace.com/play/12345');

    expect(DailyEscRecord::first()->meditation_url)
        ->toBe('https://www.headspace.com/play/12345');
});

test('store rejects a non-url meditation_url', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'meditation_completed' => true,
        'meditation_url' => 'not-a-url',
    ], $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['meditation_url']);
});

test('store fails validation when meditation_completed is missing', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [], $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['meditation_completed']);
});

test('store rejects a future record_date', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'record_date' => now()->addDay()->toDateString(),
        'meditation_completed' => true,
    ], $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['record_date']);
});

test('store accepts backfilling a past record_date', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'record_date' => now()->subDays(3)->toDateString(),
        'meditation_completed' => true,
    ], $headers)->assertStatus(201);
});

test('store rejects over-long text and a non-url image link', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'learning_text' => str_repeat('a', 5001),
        'movement_text' => str_repeat('b', 5001),
        'movement_image_url' => 'not-a-url',
        'meditation_completed' => true,
    ], $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['learning_text', 'movement_text', 'movement_image_url']);
});

test('store rejects a non-image upload', function () {
    [$user, $headers] = escActor();

    $this->post('/api/v1/public/esc/daily-records', [
        'movement_image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        'meditation_completed' => true,
    ], $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['movement_image']);
});

test('a checklist-style save can send booleans alone, with no text', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'learning_completed' => true,
        'movement_completed' => true,
        'meditation_completed' => true,
    ], $headers)
        ->assertStatus(201)
        ->assertJsonPath('data.learning_completed', true)
        ->assertJsonPath('data.movement_completed', true)
        ->assertJsonPath('data.meditation_completed', true)
        // Nothing was written, so the notes stay empty.
        ->assertJsonPath('data.learning_text', null)
        ->assertJsonPath('data.movement_text', null);
});

test('an explicit false wins over text that was also sent', function () {
    [$user, $headers] = escActor();

    // Text present but the user un-ticked the pillar — honour the flag.
    $this->postJson('/api/v1/public/esc/daily-records', [
        'learning_text' => 'Started but did not finish.',
        'learning_completed' => false,
        'meditation_completed' => false,
    ], $headers)
        ->assertStatus(201)
        ->assertJsonPath('data.learning_completed', false)
        ->assertJsonPath('data.learning_text', 'Started but did not finish.');
});

test('omitting the flags still derives them from the text (unchanged behaviour)', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'learning_text' => 'Read a chapter.',
        'meditation_completed' => true,
    ], $headers)
        ->assertStatus(201)
        ->assertJsonPath('data.learning_completed', true)
        // No movement text or image was sent, so it stays false.
        ->assertJsonPath('data.movement_completed', false);
});

test('movement is derived from an uploaded image when no flag is sent', function () {
    Storage::fake('public');

    [$user, $headers] = escActor();

    $this->post('/api/v1/public/esc/daily-records', [
        'movement_image' => UploadedFile::fake()->image('run.jpg'),
        'meditation_completed' => false,
    ], $headers)
        ->assertStatus(201)
        ->assertJsonPath('data.movement_completed', true);
});

test('index returns the current week when no range is given', function () {
    [$user, $headers] = escActor();

    $monday = now()->startOfWeek(Carbon::MONDAY);

    // Two days inside this week, one day last week (should be excluded).
    DailyEscRecord::factory()->for($user)->create(['record_date' => $monday->toDateString()]);
    DailyEscRecord::factory()->for($user)->create(['record_date' => $monday->copy()->addDays(2)->toDateString()]);
    DailyEscRecord::factory()->for($user)->create(['record_date' => $monday->copy()->subDays(2)->toDateString()]);

    $this->getJson('/api/v1/public/esc/daily-records', $headers)
        ->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('range.from', $monday->toDateString());
});

test('index filters to the requested range and only returns the owner records', function () {
    [$user, $headers] = escActor();
    $other = User::factory()->create();

    DailyEscRecord::factory()->for($user)->create([
        'record_date' => '2026-07-20',
        'learning_text' => 'Mine.',
        'learning_completed' => true,
        'meditation_completed' => true,
    ]);
    DailyEscRecord::factory()->for($user)->create(['record_date' => '2026-07-27']); // outside range
    DailyEscRecord::factory()->for($other)->create(['record_date' => '2026-07-21']); // someone else

    $this->getJson('/api/v1/public/esc/daily-records?from=2026-07-20&to=2026-07-26', $headers)
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.record_date', '2026-07-20')
        ->assertJsonPath('data.0.learning_completed', true)
        ->assertJsonPath('data.0.meditation_completed', true);
});

test('the range view returns only the date and the three ticks', function () {
    [$user, $headers] = escActor();

    DailyEscRecord::factory()->for($user)->create([
        'record_date' => '2026-07-20',
        'learning_text' => 'Should not be sent.',
        'movement_text' => 'Should not be sent either.',
        'movement_image_url' => 'https://example.test/photo.jpg',
        'meditation_url' => 'https://example.test/session',
    ]);

    $day = $this->getJson('/api/v1/public/esc/daily-records?from=2026-07-20&to=2026-07-26', $headers)
        ->assertStatus(200)
        ->json('data.0');

    // Exactly four keys — no notes, no URLs, no ids or timestamps.
    expect(array_keys($day))->toEqualCanonicalizing([
        'record_date',
        'learning_completed',
        'movement_completed',
        'meditation_completed',
    ]);
});

test('a single day still returns the full content for editing', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'learning_text' => 'Needed to prefill the edit form.',
        'meditation_completed' => true,
    ], $headers)->assertStatus(201);

    // The week view is lean, but /today keeps the text so an edit screen works.
    $this->getJson('/api/v1/public/esc/daily-records/today', $headers)
        ->assertStatus(200)
        ->assertJsonPath('data.learning_text', 'Needed to prefill the edit form.');
});

test('index rejects a to date before from', function () {
    [$user, $headers] = escActor();

    $this->getJson('/api/v1/public/esc/daily-records?from=2026-07-20&to=2026-07-10', $headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['to']);
});

test('index requires a token', function () {
    $this->getJson('/api/v1/public/esc/daily-records')->assertStatus(401);
});

test('me returns the token owner and their streaks', function () {
    [$user, $headers] = escActor();

    $user->forceFill(['current_streak' => 4, 'longest_streak' => 9])->save();

    $this->getJson('/api/v1/public/esc/auth/me', $headers)
        ->assertStatus(200)
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('streak.current', 4)
        ->assertJsonPath('streak.longest', 9);
});

test('me requires a token', function () {
    $this->getJson('/api/v1/public/esc/auth/me')->assertStatus(401);
});

test('logging consecutive days builds the current and longest streak', function () {
    [$user, $headers] = escActor();

    foreach (['2026-07-18', '2026-07-19', '2026-07-20'] as $date) {
        $this->postJson('/api/v1/public/esc/daily-records', [
            'record_date' => $date,
            'meditation_completed' => true,
        ], $headers)->assertSuccessful();
    }

    // The most recent record is 2026-07-20; "today" in the test run is later, so
    // the streak is measured against the run ending on the latest logged day.
    $user->refresh();

    expect($user->longest_streak)->toBe(3);
});

test('a missed day breaks the current streak but keeps the longest', function () {
    [$user, $headers] = escActor();

    // A 3-day run, then a gap, then a single day.
    foreach (['2026-07-10', '2026-07-11', '2026-07-12', '2026-07-15'] as $date) {
        $this->postJson('/api/v1/public/esc/daily-records', [
            'record_date' => $date,
            'meditation_completed' => true,
        ], $headers)->assertSuccessful();
    }

    $user->refresh();

    // The longest run (3 consecutive days) is remembered even though the streak
    // was broken by the gap before 2026-07-15.
    expect($user->longest_streak)->toBe(3);
    // The latest activity is a lone day well in the past, so no streak reaches
    // today — current streak is 0.
    expect($user->current_streak)->toBe(0);
});

test('backfilling a gap repairs the streak', function () {
    [$user, $headers] = escActor();

    $today = now()->toDateString();
    $twoDaysAgo = now()->subDays(2)->toDateString();

    // Log today and two days ago, leaving yesterday missing → current streak 1.
    $this->postJson('/api/v1/public/esc/daily-records', [
        'record_date' => $twoDaysAgo,
        'meditation_completed' => true,
    ], $headers)->assertSuccessful();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'record_date' => $today,
        'meditation_completed' => true,
    ], $headers)->assertSuccessful();

    expect($user->fresh()->current_streak)->toBe(1);

    // Backfill yesterday → the three days are now consecutive.
    $response = $this->postJson('/api/v1/public/esc/daily-records', [
        'record_date' => now()->subDay()->toDateString(),
        'meditation_completed' => true,
    ], $headers)->assertSuccessful();

    $response->assertJsonPath('streak.current', 3)
        ->assertJsonPath('streak.longest', 3);

    expect($user->fresh()->current_streak)->toBe(3);
    expect($user->fresh()->longest_streak)->toBe(3);
});

test('the streak is still alive from yesterday when today is not yet logged', function () {
    [$user, $headers] = escActor();

    foreach ([now()->subDays(2)->toDateString(), now()->subDay()->toDateString()] as $date) {
        $this->postJson('/api/v1/public/esc/daily-records', [
            'record_date' => $date,
            'meditation_completed' => true,
        ], $headers)->assertSuccessful();
    }

    // Nothing logged for today yet, but yesterday + the day before form a live
    // 2-day streak the user can still extend today.
    expect($user->fresh()->current_streak)->toBe(2);
});

test('rejects a request with no token', function () {
    $this->postJson('/api/v1/public/esc/daily-records', [
        'meditation_completed' => true,
    ])->assertStatus(401);
});

test('rejects a request with a bogus token', function () {
    $this->postJson('/api/v1/public/esc/daily-records', [
        'meditation_completed' => true,
    ], ['Authorization' => 'Bearer 1|totallymadeuptoken'])->assertStatus(401);
});

test('a revoked token no longer works', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/auth/logout', [], $headers)
        ->assertStatus(200);

    // The test app instance is reused across requests, so the sanctum guard
    // still holds the user it resolved a moment ago. Production gets a fresh
    // instance per request; drop the cached guard to match.
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'meditation_completed' => true,
    ], $headers)->assertStatus(401);
});

test('today returns null when nothing is logged yet', function () {
    [$user, $headers] = escActor();

    $this->getJson('/api/v1/public/esc/daily-records/today', $headers)
        ->assertStatus(200)
        ->assertJsonPath('data', null)
        ->assertJsonPath('streak.current', 0);
});

test('today returns the record once logged', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'learning_text' => 'Read a chapter.',
        'meditation_completed' => true,
    ], $headers)->assertStatus(201);

    $this->getJson('/api/v1/public/esc/daily-records/today', $headers)
        ->assertStatus(200)
        ->assertJsonPath('data.record_date', now()->toDateString())
        ->assertJsonPath('data.learning_text', 'Read a chapter.')
        ->assertJsonPath('data.meditation_completed', true)
        ->assertJsonPath('streak.current', 1);
});

test('a partial edit does not blank the fields it omits', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'learning_text' => 'Keep me.',
        'movement_text' => 'Keep me too.',
        'meditation_completed' => false,
    ], $headers)->assertStatus(201);

    // Save only the meditation toggle.
    $this->patchJson('/api/v1/public/esc/daily-records/today', [
        'meditation_completed' => true,
    ], $headers)
        ->assertStatus(200)
        ->assertJsonPath('data.meditation_completed', true)
        ->assertJsonPath('data.learning_text', 'Keep me.')
        ->assertJsonPath('data.movement_text', 'Keep me too.');
});

test('a partial edit creates the row when today is not logged yet', function () {
    [$user, $headers] = escActor();

    $this->patchJson('/api/v1/public/esc/daily-records/today', [
        'learning_completed' => true,
    ], $headers)
        ->assertStatus(201)
        ->assertJsonPath('created', true)
        ->assertJsonPath('data.learning_completed', true)
        ->assertJsonPath('streak.current', 1);
});

test('an edit can explicitly clear a note by sending null', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'learning_text' => 'Remove me.',
        'meditation_completed' => true,
    ], $headers)->assertStatus(201);

    $this->patchJson('/api/v1/public/esc/daily-records/today', [
        'learning_text' => null,
    ], $headers)->assertStatus(200);

    // Null fields are dropped from the payload entirely.
    expect(DailyEscRecord::first()->learning_text)->toBeNull();
    expect(DailyEscRecord::first()->learning_completed)->toBeFalse();
});

test('null fields are omitted from the response', function () {
    [$user, $headers] = escActor();

    $response = $this->postJson('/api/v1/public/esc/daily-records', [
        'learning_completed' => true,
        'movement_completed' => true,
        'meditation_completed' => true,
    ], $headers)->assertStatus(201);

    $data = $response->json('data');

    expect($data)->not->toHaveKey('learning_text');
    expect($data)->not->toHaveKey('movement_image_url');
    expect($data)->toHaveKey('learning_completed');
    // record_date is a plain date, not a timezone-shifted ISO datetime.
    expect($data['record_date'])->toBe(now()->toDateString());
});

test('the today endpoints require a token', function () {
    $this->getJson('/api/v1/public/esc/daily-records/today')->assertStatus(401);
    $this->patchJson('/api/v1/public/esc/daily-records/today', [])->assertStatus(401);
});

test('store sets the completion flags from what was submitted', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'record_date' => '2026-07-20',
        'learning_text' => 'Read a chapter.',
        'meditation_completed' => false,
    ], $headers)->assertStatus(201);

    $record = DailyEscRecord::where('user_id', $user->id)->sole();

    expect($record->learning_completed)->toBeTrue()
        ->and($record->movement_completed)->toBeFalse()
        ->and($record->meditation_completed)->toBeFalse();
});

test('a movement photo alone counts as movement completed', function () {
    Storage::fake('public');

    [$user, $headers] = escActor();

    $this->post('/api/v1/public/esc/daily-records', [
        'record_date' => '2026-07-20',
        'movement_image' => UploadedFile::fake()->image('run.jpg'),
        'meditation_completed' => false,
    ], $headers)->assertStatus(201);

    expect(DailyEscRecord::where('user_id', $user->id)->sole()->movement_completed)
        ->toBeTrue();
});

test('clearing a pillar clears its completion flag', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'record_date' => '2026-07-20',
        'learning_text' => 'Something.',
        'meditation_completed' => true,
    ], $headers)->assertStatus(201);

    $this->postJson('/api/v1/public/esc/daily-records', [
        'record_date' => '2026-07-20',
        'meditation_completed' => true,
    ], $headers)->assertStatus(200);

    expect(DailyEscRecord::where('user_id', $user->id)->sole()->learning_completed)
        ->toBeFalse();
});

test('index returns the completion flags', function () {
    [$user, $headers] = escActor();

    $this->postJson('/api/v1/public/esc/daily-records', [
        'record_date' => Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString(),
        'movement_text' => 'Long walk.',
        'meditation_completed' => true,
    ], $headers)->assertStatus(201);

    $this->getJson('/api/v1/public/esc/daily-records', $headers)
        ->assertOk()
        ->assertJsonPath('data.0.learning_completed', false)
        ->assertJsonPath('data.0.movement_completed', true)
        ->assertJsonPath('data.0.meditation_completed', true);
});
