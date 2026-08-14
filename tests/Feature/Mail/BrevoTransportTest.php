<?php

use App\Mail\BrevoTransport;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

/**
 * The transport turns a Symfony message into Brevo's JSON shape. Everything
 * here fakes the HTTP call — no key is real and nothing leaves the machine.
 */
const BREVO_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

/** Point the app at the Brevo mailer with a fake key, and stub Brevo's reply. */
function usingBrevo(array $response = ['messageId' => '<abc@smtp-relay.mailin.fr>'], int $status = 201): void
{
    config([
        'mail.default' => 'brevo',
        'services.brevo.key' => 'test-key',
        'services.brevo.endpoint' => BREVO_ENDPOINT,
        'mail.from.address' => 'hello@artemis.ph',
        'mail.from.name' => 'Artemis',
    ]);

    Http::fake([BREVO_ENDPOINT => Http::response($response, $status)]);
}

test('a sent message becomes a Brevo API call', function () {
    usingBrevo();

    Mail::raw('Hello there', function ($message) {
        $message->to('someone@example.com', 'Someone')
            ->cc('boss@example.com')
            ->bcc('archive@example.com')
            ->replyTo('support@artemis.ph', 'Artemis Support')
            ->subject('A test');
    });

    Http::assertSent(function ($request) {
        $body = $request->data();

        expect($request->url())->toBe(BREVO_ENDPOINT)
            ->and($request->method())->toBe('POST')
            ->and($request->header('api-key'))->toBe(['test-key'])
            ->and($body['sender'])->toBe(['email' => 'hello@artemis.ph', 'name' => 'Artemis'])
            ->and($body['subject'])->toBe('A test')
            ->and($body['replyTo'])->toBe(['email' => 'support@artemis.ph', 'name' => 'Artemis Support'])
            ->and($body['textContent'])->toContain('Hello there');

        return true;
    });
});

test('to, cc and bcc stay in their own fields', function () {
    usingBrevo();

    Mail::raw('Body', function ($message) {
        $message->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Split');
    });

    Http::assertSent(function ($request) {
        $body = $request->data();

        // The envelope flattens all three together; only the To line belongs
        // in `to`, or the copied addresses would each get a second copy.
        expect(array_column($body['to'], 'email'))->toBe(['to@example.com'])
            ->and(array_column($body['cc'], 'email'))->toBe(['cc@example.com'])
            ->and(array_column($body['bcc'], 'email'))->toBe(['bcc@example.com']);

        return true;
    });
});

test('empty cc and bcc are left out entirely', function () {
    usingBrevo();

    Mail::raw('Body', fn ($message) => $message->to('to@example.com')->subject('Plain'));

    Http::assertSent(function ($request) {
        expect($request->data())->not->toHaveKey('cc')
            ->and($request->data())->not->toHaveKey('bcc')
            ->and($request->data())->not->toHaveKey('replyTo');

        return true;
    });
});

test('an attachment rides along base64 encoded', function () {
    usingBrevo();

    Mail::raw('See attached', function ($message) {
        $message->to('to@example.com')
            ->subject('With a file')
            ->attachData('the,csv,contents', 'report.csv', ['mime' => 'text/csv']);
    });

    Http::assertSent(function ($request) {
        $attachment = $request->data()['attachment'][0];

        expect($attachment['name'])->toBe('report.csv')
            ->and(base64_decode($attachment['content']))->toBe('the,csv,contents');

        return true;
    });
});

test('an HTML mail sends both the html and the text part', function () {
    usingBrevo();

    $user = User::factory()->create(['email' => 'user@example.com']);

    // Any markdown notification will do — this one is what the app really
    // sends on a password reset, so it is the realistic HTML+text case.
    $user->notify(new ResetPassword('a-token'));

    Http::assertSent(function ($request) {
        $body = $request->data();

        // The same content reaches Brevo in both parts, not just the HTML one.
        expect($body['htmlContent'])->toContain('Reset Password')
            ->and($body['textContent'])->toContain('Reset Password')
            ->and($body['subject'])->toContain('Reset Password')
            ->and(array_column($body['to'], 'email'))->toBe(['user@example.com']);

        return true;
    });
});

test('a refusal from Brevo surfaces its reason, not a blank failure', function () {
    usingBrevo(['code' => 'invalid_parameter', 'message' => 'Sender email is not valid'], 400);

    expect(fn () => Mail::raw('Body', fn ($message) => $message->to('to@example.com')->subject('Doomed')))
        ->toThrow(TransportException::class, 'Sender email is not valid');
});

test('a missing key fails at construction rather than as a 401 later', function () {
    expect(fn () => new BrevoTransport(''))
        ->toThrow(TransportException::class, 'BREVO_API_KEY');
});

test('the message id from Brevo is kept on the message', function () {
    usingBrevo(['messageId' => '<kept@smtp-relay.mailin.fr>']);

    $sent = Mail::raw('Body', fn ($message) => $message->to('to@example.com')->subject('Tracked'));

    // Mail::raw returns the SentMessage wrapper; the id is what ties this send
    // to a row in Brevo's own logs.
    expect($sent->getSymfonySentMessage()->getMessageId())->toBe('<kept@smtp-relay.mailin.fr>');
});
