<?php

namespace App\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Sends mail through Brevo's transactional API instead of SMTP.
 *
 * Brevo's own relay would do the same job with no code at all, but it needs
 * port 587 open outbound and plenty of hosts close it. This goes over HTTPS,
 * and the reply carries a message id worth keeping — it's what ties a send from
 * here to a row in Brevo's logs when someone asks where their email went.
 */
class BrevoTransport extends AbstractTransport
{
    /**
     * Headers Brevo models as fields of its own. Passing them through as raw
     * headers as well would put two From lines on the message.
     */
    private const OWN_HEADERS = [
        'from', 'to', 'cc', 'bcc', 'reply-to', 'sender',
        'subject', 'content-type', 'mime-version', 'date', 'message-id',
    ];

    public function __construct(
        #[\SensitiveParameter]
        private readonly string $key,
        private readonly string $endpoint = 'https://api.brevo.com/v3/smtp/email',
        private readonly int $timeout = 15,
    ) {
        if ($this->key === '') {
            // Better here than as a 401 from Brevo an hour into a queue backlog.
            throw new TransportException('No Brevo API key is configured. Set BREVO_API_KEY.');
        }

        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        try {
            $response = Http::withHeader('api-key', $this->key)
                ->acceptJson()
                ->timeout($this->timeout)
                ->post($this->endpoint, $this->payload($email, $message->getEnvelope()));
        } catch (ConnectionException $e) {
            // Symfony retries a TransportException; a raw ConnectionException
            // would escape the mailer and fail the queued job outright.
            throw new TransportException('Could not reach the Brevo API: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            // Brevo refuses with {"code": "...", "message": "..."}. Log the reply
            // whole — it names the server's own IP and the account's setup, which
            // is exactly what whoever fixes this needs and exactly what nobody
            // waiting on a signup form should ever be shown.
            Log::error('Brevo refused a message.', [
                'status' => $response->status(),
                'code' => $response->json('code'),
                'reason' => $response->json('message') ?: $response->body(),
            ]);

            throw new TransportException($this->refusal($response));
        }

        if ($id = $response->json('messageId')) {
            $message->setMessageId((string) $id);
            $email->getHeaders()->addHeader('X-Brevo-Message-ID', (string) $id);
        }
    }

    /**
     * Why Brevo said no, in one line, for the log and the Sentry issue.
     *
     * Named separately from the raw reply so the common refusals read as the
     * settings they are rather than as a fault in the app.
     */
    private function refusal(Response $response): string
    {
        $reason = (string) ($response->json('message') ?: $response->body());

        // The refusal that looks like a bug and isn't: Brevo only answers API
        // calls from addresses listed under Security → Authorised IPs, so a new
        // server — or one whose address moved — is turned away until it's added.
        // The reply quotes the address; this doesn't, because this string is
        // allowed to travel further than the log entry above.
        if ($response->status() === 401 && str_contains(strtolower($reason), 'ip address')) {
            return "Brevo refused the message: this server's IP address is not on the account's authorised IP list. Add it under Brevo → Security → Authorised IPs.";
        }

        return sprintf('Brevo rejected the message (HTTP %d): %s', $response->status(), $reason);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Email $email, Envelope $envelope): array
    {
        // The envelope's sender is the return path, which is the from address
        // for everything this app sends; the header is the one to prefer.
        $from = $email->getFrom()[0] ?? $envelope->getSender();
        $replyTo = $email->getReplyTo()[0] ?? null;

        $payload = [
            'sender' => $this->address($from),
            'to' => $this->addresses($this->recipients($email, $envelope)),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            // Brevo takes a single reply-to, where a message may carry several.
            'replyTo' => $replyTo ? $this->address($replyTo) : null,
            'subject' => $email->getSubject(),
            'htmlContent' => $this->body($email->getHtmlBody()),
            'textContent' => $this->body($email->getTextBody()),
            'attachment' => $this->attachments($email),
            'headers' => $this->headers($email),
        ];

        // Brevo reads an empty cc/bcc as a malformed field rather than as none.
        return array_filter($payload, fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @return array<string, string>
     */
    private function address(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName(),
        ], fn (string $value) => $value !== '');
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, array<string, string>>
     */
    private function addresses(array $addresses): array
    {
        return array_values(array_map($this->address(...), $addresses));
    }

    /**
     * Everyone on the To line. The envelope holds every recipient flattened
     * together, so the copied ones have to come back out.
     *
     * @return array<int, Address>
     */
    private function recipients(Email $email, Envelope $envelope): array
    {
        $copied = array_merge($email->getCc(), $email->getBcc());

        return array_filter(
            $envelope->getRecipients(),
            fn (Address $address) => ! in_array($address, $copied, true)
        );
    }

    /** A body is a string or a stream, depending on how it was attached. */
    private function body(mixed $body): ?string
    {
        if (is_resource($body)) {
            rewind($body);

            return stream_get_contents($body) ?: null;
        }

        return $body;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function attachments(Email $email): array
    {
        return array_values(array_map(function (DataPart $attachment) {
            $headers = $attachment->getPreparedHeaders();

            return [
                'name' => $headers->getHeaderParameter('Content-Disposition', 'filename') ?: 'attachment',
                // getBody() is the raw content — Brevo wants it base64 encoded
                // in one unbroken run, not MIME's line-wrapped form.
                'content' => base64_encode($attachment->getBody()),
            ];
        }, $email->getAttachments()));
    }

    /**
     * Anything Brevo doesn't already model — the X- headers Laravel tags mail
     * with, and whatever a caller added by hand.
     *
     * @return array<string, string>
     */
    private function headers(Email $email): array
    {
        $headers = [];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (in_array($name, self::OWN_HEADERS, true)) {
                continue;
            }

            $headers[$header->getName()] = $header->getBodyAsString();
        }

        return $headers;
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
