# WellSync → Artemis ESC API

How the WellSync app saves daily Extreme Self-Care (ESC) records into Artemis.

**No workspace API key is involved.** Users authenticate as themselves with a
Sanctum bearer token. The record always belongs to the token owner — there is
no `email` or `user_id` field, so WellSync cannot write a record for anyone
other than the logged-in user.

---

## Environment variables

### On the WellSync side

| Variable | Example | Purpose |
| --- | --- | --- |
| `ARTEMIS_URL` | `https://artemis.test` | Base URL of the Artemis app. No trailing slash. |

Wire it into `config/services.php`:

```php
'artemis' => [
    'url' => env('ARTEMIS_URL', 'https://artemis.test'),
],
```

There is deliberately **no** `ARTEMIS_API_KEY`. Auth is per-user.

### On the Artemis side

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_URL` | `https://artemis.test` | Base for generated URLs. |
| `ASSET_URL` | *(unset)* | Set this to make `movement_image_url` absolute instead of `/storage/...`. |
| `FILESYSTEM_DISK` | `local` | Unrelated to ESC — uploads always go to the `public` disk explicitly. |
| `SANCTUM_STATEFUL_DOMAINS` | *(empty)* | Deliberately empty. Keeps the API token-only; see "Why no stateful domains" below. |

Requires `php artisan storage:link` (already done in this repo) so uploaded
images are reachable at `/storage/...`.

---

## Endpoints

| Method | Path | Auth |
| --- | --- | --- |
| `POST` | `/api/v1/public/esc/auth/login` | none (throttled) |
| `GET` | `/api/v1/public/esc/auth/me` | bearer token |
| `GET` | `/api/v1/public/esc/daily-records` | bearer token |
| `POST` | `/api/v1/public/esc/daily-records` | bearer token |
| `GET` | `/api/v1/public/esc/notifications` | bearer token |
| `PUT`/`PATCH` | `/api/v1/public/esc/notifications` | bearer token |
| `POST` | `/api/v1/public/esc/auth/logout` | bearer token |

Always send `Accept: application/json` on every request. Without it, validation
failures return a **302 redirect** instead of a 422 JSON body.

---

## 1. Login

```http
POST /api/v1/public/esc/auth/login
Content-Type: application/json
Accept: application/json

{
  "email": "employee@company.com",
  "password": "their-artemis-password",
  "device_name": "wellsync"
}
```

### Variables

| Field | Type | Rules | Required |
| --- | --- | --- | --- |
| `email` | string | `email` | **yes** |
| `password` | string | `string` | **yes** |
| `device_name` | string | `max:255` | no — defaults to `wellsync` |

### Responses

**200**

```json
{
  "user": { "id": 42, "name": "Jane Dela Cruz", "email": "employee@company.com", "is_super_admin": false },
  "token": "7|Xq2mB9vN4kL8pR3tY6wZ1cF5hJ0dS..."
}
```

**401** `{"error": "Invalid credentials."}` — same response and same timing
whether the email exists or not, so it can't be used to enumerate accounts.

**422** — `email` or `password` missing/malformed.

**429** — rate limited. **5 attempts per minute** per email+IP, plus 30/min per
IP overall.

Logging in again with the same `device_name` **revokes the previous token**, so
tokens don't accumulate. The token is returned once and stored hashed — you
cannot read it back later.

---

## 1a. Profile / streak summary

A lightweight read for the streak header. Returns the token owner plus their
maintained streaks, so you don't have to wait for a save response to show them.

```http
GET /api/v1/public/esc/auth/me
Authorization: Bearer 7|Xq2mB9...
Accept: application/json
```

**200**

```json
{
  "user": { "id": 42, "name": "Jane Dela Cruz", "email": "employee@company.com", "is_super_admin": false },
  "streak": { "current": 5, "longest": 12 }
}
```

**401** — token missing, invalid, or revoked.

Use `streak.current` for `getStreak()`.

---

## 1b. Read a week of records

Returns the token owner's records for a date range, ordered oldest-first. Compute
the Monday–Sunday of the week you want and pass it as `from`/`to`.

```http
GET /api/v1/public/esc/daily-records?from=2026-07-20&to=2026-07-26
Authorization: Bearer 7|Xq2mB9...
Accept: application/json
```

### Query params

| Field | Type | Rules | Required | Default |
| --- | --- | --- | --- | --- |
| `from` | string | `date` (`YYYY-MM-DD`) | no | Monday of the current week (server time) |
| `to` | string | `date`, `after_or_equal:from` | no | Sunday of the current week |

Omit both to get the current week. Only days that have a record are returned —
a week with three logged days returns three entries, not seven; fill the gaps
client-side.

### Responses

**200**

```json
{
  "data": [
    {
      "id": 1,
      "record_date": "2026-07-20",
      "learning_text": "Read two chapters of Deep Work.",
      "movement_text": "5km run before shift.",
      "movement_image_url": "/storage/esc/movement/42/AbC123.jpg",
      "meditation_completed": true,
      "meditation_url": "https://www.headspace.com/play/12345",
      "submitted_at": "2026-07-20T09:12:44+00:00"
    }
  ],
  "range": { "from": "2026-07-20", "to": "2026-07-26" }
}
```

**422** — `to` is before `from`, or a date is malformed.
**401** — token missing, invalid, or revoked.

Unlike the save response, **`record_date` here is a plain `YYYY-MM-DD` string**
(no time component), so you can key records by day directly.

### Mapping to `DayRecord`

```ts
// getWeek(): keep the current Mon–Sun and map each record
const byDate = new Map(data.map((r) => [r.record_date, r]));

const day = byDate.get(dateStr);
const record: DayRecord = {
    learning: day?.learning_text ?? null,
    movement: { note: day?.movement_text ?? null, media: day?.movement_image_url ?? null },
    meditation: day?.meditation_completed ?? false,
};
```

---

## 1c. Reminder settings

Per-user ESC reminder settings, stored in `esc_notifications` (one row per user).

### Read

```http
GET /api/v1/public/esc/notifications
Authorization: Bearer 7|Xq2mB9...
Accept: application/json
```

**200**

```json
{
  "data": {
    "reminder_enabled": true,
    "reminder_time": "20:00",
    "reminder_timezone": "Asia/Manila",
    "reminder_style": "gentle",
    "last_reminded_at": null
  }
}
```

A user who has never saved settings gets the defaults above, and the row is
created on first read so the reminder scheduler can see every user.

### Save

```http
PUT /api/v1/public/esc/notifications
Authorization: Bearer 7|Xq2mB9...
Content-Type: application/json
Accept: application/json

{
  "reminder_enabled": true,
  "reminder_time": "07:30",
  "reminder_timezone": "Asia/Singapore",
  "reminder_style": "firm"
}
```

`PATCH` is accepted at the same path and behaves identically.

| Field | Type | Rules | Required |
| --- | --- | --- | --- |
| `reminder_enabled` | bool | `boolean` | no |
| `reminder_time` | string | `H:i` (`"20:00"`) or `H:i:s` (`"20:00:00"`) | no |
| `reminder_timezone` | string | a valid IANA identifier, e.g. `Asia/Manila` | no |
| `reminder_style` | string | `max:16` | no |

Rules worth spelling out:

- **Every field is optional** — send only what changed. Omitted fields keep
  their current value, so a lone `{"reminder_enabled": false}` is a valid body.
- **`reminder_time` is wall-clock in `reminder_timezone`**, not UTC. `20:00`
  means 8pm where the employee is.
- **`reminder_time` always comes back as `HH:MM`** even if you sent seconds.
- **`last_reminded_at` is read-only.** It's stamped by the reminder scheduler so
  the same day can't be reminded twice; sending it does nothing.

**200** — returns the full saved settings in the same shape as the read.
**401** — token missing, invalid, or revoked.
**422** — malformed time, unknown timezone, or a style over 16 characters.

---

## 2. Save an ESC record

```http
POST /api/v1/public/esc/daily-records
Authorization: Bearer 7|Xq2mB9vN4kL8pR3tY6wZ1cF5hJ0dS...
Content-Type: application/json
Accept: application/json

{
  "record_date": "2026-07-21",
  "learning_text": "Read two chapters of Deep Work.",
  "movement_text": "5km run before shift.",
  "meditation_completed": true,
  "meditation_url": "https://www.headspace.com/play/12345"
}
```

### Variables and validation

| Field | Type | Rules | Required | Default |
| --- | --- | --- | --- | --- |
| `record_date` | string | `date`, `before_or_equal:today` | no | today (server date) |
| `learning_text` | string | `max:5000` | no | `null` |
| `movement_text` | string | `max:5000` | no | `null` |
| `movement_image` | file | `image`, `mimes:jpg,jpeg,png,webp`, `max:10240` (10 MB) | no | keeps existing |
| `movement_image_url` | string | `url`, `max:2048` | no | keeps existing |
| `meditation_completed` | boolean | `boolean` | **yes** | — |
| `meditation_url` | string | `url`, `max:2048` | no | `null` |

Rules worth spelling out:

- **Only `meditation_completed` is required.** Partial submissions are valid;
  the user can fill the rest in later by resubmitting the same date.
- **`record_date` cannot be in the future.** Backfilling a missed day is
  allowed and expected. Format `YYYY-MM-DD`.
- **`meditation_completed` accepts** `true`, `false`, `1`, `0`, `"1"`, `"0"`.
  Over multipart you must send `1`/`0` — the strings `"true"`/`"false"` fail
  Laravel's `boolean` rule.
- **`movement_image` and `movement_image_url` are alternatives.** If both are
  sent, the uploaded file wins. HEIC is not accepted — convert on the WellSync
  side before uploading.
- **`meditation_url` is a hosted link only** — proof of meditation such as a
  Headspace/Calm session URL or an image you host elsewhere. Unlike movement,
  there is no file-upload counterpart; it must be a valid `http(s)` URL. Omitting
  it on a resubmit sets it back to `null` (it is not "keep existing" like the
  movement image).
- **Unknown fields are ignored**, not rejected. Sending `email` or `user_id`
  does nothing; the record is always written against the token owner.

### Responses

**201** — first submission for that date (`"created": true`).
**200** — record already existed and was updated (`"created": false`).

```json
{
  "data": {
    "id": 1,
    "user_id": 42,
    "record_date": "2026-07-21T00:00:00.000000Z",
    "learning_text": "Read two chapters of Deep Work.",
    "movement_text": "5km run before shift.",
    "movement_image_url": "/storage/esc/movement/42/AbC123.jpg",
    "meditation_completed": true,
    "meditation_url": "https://www.headspace.com/play/12345",
    "submitted_at": "2026-07-21T09:12:44.000000Z",
    "created_at": "2026-07-21T09:12:44.000000Z",
    "updated_at": "2026-07-21T09:12:44.000000Z"
  },
  "created": true,
  "streak": {
    "current": 5,
    "longest": 12
  }
}
```

**401** — token missing, invalid, or revoked. Send the user back to login.

**422** — validation failed:

```json
{
  "message": "The meditation completed field is required.",
  "errors": {
    "meditation_completed": ["The meditation completed field is required."]
  }
}
```

### Response fields

| Field | Type | Notes |
| --- | --- | --- |
| `data.id` | int | Record primary key. |
| `data.user_id` | int | Artemis user ID — always the token owner. |
| `data.record_date` | ISO datetime | Cast from a `date` column; the time is always midnight. |
| `data.learning_text` | string\|null | |
| `data.movement_text` | string\|null | |
| `data.movement_image_url` | string\|null | Relative `/storage/...` unless `ASSET_URL` is set. |
| `data.meditation_completed` | bool | |
| `data.meditation_url` | string\|null | Hosted proof-of-meditation link, echoed back as sent. |
| `data.submitted_at` | ISO datetime | Refreshed on every submit, including updates. |
| `created` | bool | `true` = new row (201), `false` = updated (200). |
| `streak.current` | int | Consecutive days ending today (or yesterday if today isn't logged yet). A gap of 2+ days resets it to 0. Recomputed on every submit. |
| `streak.longest` | int | Longest run of consecutive days the user has ever logged. Never decreases. |

---

## 3. Uploading a movement photo

Must be `multipart/form-data`, not JSON:

```bash
curl -X POST "$ARTEMIS_URL/api/v1/public/esc/daily-records" \
  -H "Authorization: Bearer 7|Xq2mB9..." \
  -H "Accept: application/json" \
  -F "meditation_completed=1" \
  -F "movement_text=Gym session" \
  -F "movement_image=@/path/to/photo.jpg"
```

Files are stored at `esc/movement/{user_id}/{random}.{ext}` on the `public`
disk. Replacing an image on a later submission deletes the old file; images
supplied via `movement_image_url` (hosted elsewhere) are never deleted.

---

## 4. Logout

```http
POST /api/v1/public/esc/auth/logout
Authorization: Bearer 7|Xq2mB9...
Accept: application/json
```

**200** `{"message": "Token revoked."}` — revokes only the token used for this
request; other devices stay signed in.

---

## Laravel client example

```php
use Illuminate\Support\Facades\Http;

// --- Login -------------------------------------------------------------
$login = Http::acceptJson()->post(config('services.artemis.url').'/api/v1/public/esc/auth/login', [
    'email' => $request->email,
    'password' => $request->password,
    'device_name' => 'wellsync',
]);

if ($login->status() === 429) {
    return back()->withErrors(['email' => 'Too many attempts. Try again shortly.']);
}

if ($login->failed()) {
    return back()->withErrors(['email' => 'Invalid Artemis credentials.']);
}

session([
    'artemis_token' => $login->json('token'),
    'artemis_user' => $login->json('user'),
]);
```

```php
// --- Save today's record ----------------------------------------------
$response = Http::withToken(session('artemis_token'))
    ->acceptJson()
    ->post(config('services.artemis.url').'/api/v1/public/esc/daily-records', [
        'record_date' => $request->input('record_date'),   // omit for today
        'learning_text' => $request->input('learning_text'),
        'movement_text' => $request->input('movement_text'),
        'meditation_completed' => $request->boolean('meditation_completed'),
        'meditation_url' => $request->input('meditation_url'),   // optional hosted link
    ]);

if ($response->status() === 401) {
    session()->forget('artemis_token');

    return redirect()->route('login');
}

if ($response->status() === 422) {
    return back()->withErrors($response->json('errors'));
}

$record = $response->json('data');
$wasNew = $response->json('created');
```

```php
// --- With a photo ------------------------------------------------------
Http::withToken(session('artemis_token'))
    ->acceptJson()
    ->attach('movement_image', file_get_contents($path), 'photo.jpg')
    ->post(config('services.artemis.url').'/api/v1/public/esc/daily-records', [
        'movement_text' => $request->input('movement_text'),
        'meditation_completed' => $request->boolean('meditation_completed') ? 1 : 0,
    ]);
```

---

## Gotchas

- **`created: false` with a 200 is not an error.** The user already submitted
  for that date and the row was updated. One record per user per day is
  enforced by a unique index on `(user_id, record_date)`.
- **Always send `Accept: application/json`**, or 422s become 302 redirects.
- **Booleans over multipart** must be `1`/`0`, not `true`/`false`.
- **Resubmitting without `movement_image` keeps the existing image** rather than
  clearing it. There is currently no way to remove an image via the API.
- **Tokens never expire on their own.** They die on logout, or when the same
  `device_name` logs in again. Set `expiration` in Artemis' `config/sanctum.php`
  if you want a TTL.
- **The user must already exist in Artemis.** This API authenticates against
  existing Artemis accounts; it does not create them.
- **`record_date` is returned as a full ISO datetime**, not `YYYY-MM-DD`, because
  the model casts it to a date object. Format it on the WellSync side.

### Why no stateful domains

`sanctum.stateful` is deliberately empty in Artemis. Sanctum is used here only
for token auth from separate first-party apps; Artemis' own Inertia frontend
runs on the `web` session guard. Leaving domains in that list would let the
`sanctum` guard fall back to session cookies, which means a revoked token could
still authenticate from a browser on that host.

---

## Not yet built

- **`reminder_time` is not wired up.** The `users.reminder_time` column exists
  but nothing reads or writes it yet. (Reading records and streaks *is* now
  supported — see `GET /esc/daily-records` and `GET /esc/auth/me`.)
