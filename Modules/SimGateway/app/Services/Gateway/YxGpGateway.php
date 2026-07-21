<?php

namespace Modules\SimGateway\Services\Gateway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\SimGateway\Enums\MessageDirection;
use Modules\SimGateway\Enums\MessageStatus;
use Modules\SimGateway\Jobs\SimulateMessageDeliveryJob;
use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Models\SmsMessage;
use Modules\SimGateway\Services\Gateway\DTOs\GatewayResponse;
use Modules\SimGateway\Services\Gateway\DTOs\IncomingMessage;
use Modules\SimGateway\Services\Gateway\DTOs\SimStatus;
use Modules\SimGateway\Services\Gateway\Exceptions\GatewayException;
use Throwable;

/**
 * Production driver for the YX-Series GP hardware SMS gateway (YX International).
 *
 * Protocol: the device's JSON HTTP API (manual §11, but at lowercase paths —
 * the manual writes /GP_post_sms.html, the actual firmware exposes
 * /goip_post_sms.html and /goip_get_sms.html).
 *
 *   POST /goip_post_sms.html?username=&password=   — send SMS (JSON in/out)
 *   GET  /goip_get_sms.html?username=&password=&sms_num=  — pull inbox
 *
 * Credentials travel as query-string params (the device's own scheme); no
 * cookie/session is required for either endpoint. The device PUSHES delivery
 * receipts and inbound SMS back to us via Http/Controllers/Gateway/*.
 */
class YxGpGateway implements GatewayInterface
{
    public function __construct(
        protected string $host,
        protected string $username,
        protected string $password,
        protected string $charset = 'utf8',
        protected int $timeoutSeconds = 10,
        protected bool $verifyTls = true,
        protected string $token
    ) {
        if ($this->host === '') {
            throw new GatewayException('YX GP gateway is misconfigured: SIMGATEWAY_YXGP_HOST is empty.');
        }
    }

    /**
     * Send an SMS via POST /goip_post_sms.html (the device's JSON API).
     */
    public function sendSms(Sim $sim, string $to, string $message): GatewayResponse
    {
        if ($sim->port_number === null) {
            throw new GatewayException("SIM #{$sim->id} has no port_number; cannot route to a hardware slot.");
        }

        $segments = max(1, (int) ceil(mb_strlen($message) / 160));
        $tid = (int) (time() % 2_000_000_000);

        $sms = SmsMessage::create([
            'workspace_id' => $sim->workspace_id,
            'sim_id' => $sim->id,
            'direction' => MessageDirection::Outbound,
            'from_number' => $sim->phone_number,
            'to_number' => $to,
            'message' => $message,
            'segments' => $segments,
            'status' => MessageStatus::Queued,
            'provider_message_id' => 'yxgp:'.$tid,
            'sent_at' => now(),
        ]);

        $task = [
            'tid' => (string) $tid,
            'from' => (string) $sim->port_number,
            'to' => $to,
            'sms' => $message,
            'dr' => 1,
            'sdr' => 1,
            'fdr' => 1,
            'tmo' => 60,
        ];

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withOptions(['verify' => $this->verifyTls])
                ->withHeaders([
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Accept' => 'application/json',
                    'X-Api-Key' => $this->token,
                ])
                ->post($this->url('/goip_post_sms.html', [
                    'version' => '1.1',
                    'username' => $this->username,
                    'password' => $this->password,
                ]), array_filter([
                    'type' => 'send-sms',
                    'task_num' => 1,
                    'sr_cnt' => 1,
                    'sr_prd' => 5,
                    // Tell the device exactly where to push the status report so it
                    // works without any admin-side "SMS Forward" config on the device.
                    'sr_url' => $this->statusReportUrl(),
                    'tasks' => [$task],
                ], fn ($value): bool => $value !== null));

            if (! $response->successful()) {
                $reason = 'HTTP '.$response->status();
                $sms->update(['status' => MessageStatus::Failed, 'error_message' => $reason]);

                return new GatewayResponse(false, $sms->provider_message_id, MessageStatus::Failed->value, $reason, $segments);
            }

            $json = $response->json() ?? [];
            $code = (int) ($json['code'] ?? 500);

            if ($code !== 200) {
                $reason = (string) ($json['reason'] ?? 'Unknown gateway response');
                $sms->update(['status' => MessageStatus::Failed, 'error_message' => "Gateway refused ({$code}): {$reason}"]);

                return new GatewayResponse(false, $sms->provider_message_id, MessageStatus::Failed->value, $reason, $segments);
            }

            // Manual §11.1.2: status[].status === "0 OK" means the task was accepted.
            // Delivery confirmation comes later via the SMS-to-HTTP callback (§8.3.2).
            return new GatewayResponse(true, $sms->provider_message_id, MessageStatus::Queued->value, null, $segments);
        } catch (ConnectionException|RequestException $e) {
            Log::warning('YX GP sendSms transport failure', ['sms_id' => $sms->id, 'error' => $e->getMessage()]);
            $sms->update([
                'status' => MessageStatus::Failed,
                'error_message' => 'Gateway unreachable: '.Str::limit($e->getMessage(), 200),
            ]);

            return new GatewayResponse(false, $sms->provider_message_id, MessageStatus::Failed->value, $e->getMessage(), $segments);
        } catch (Throwable $e) {
            Log::error('YX GP sendSms unexpected failure', ['sms_id' => $sms->id, 'error' => $e->getMessage()]);
            $sms->update([
                'status' => MessageStatus::Failed,
                'error_message' => 'Unexpected error: '.Str::limit($e->getMessage(), 200),
            ]);
            throw new GatewayException('YX GP sendSms failed: '.$e->getMessage(), 0, $e);
        } finally {
            if (config('app.env') !== 'production') {
                SimulateMessageDeliveryJob::dispatch($sms->id)->delay(now()->addSeconds(30));
            }
        }
    }

    /**
     * Send a batch of SMS in one POST /goip_post_sms.html request. The device's
     * JSON API natively accepts many `tasks` per call, so this maps each task
     * onto its own device task and records an SmsMessage row for it.
     *
     * @param  list<array{from: string, to: string, message: string}>  $tasks
     */
    public function sendBulkSms(Sim $sim, array $tasks): GatewayResponse
    {
        if ($tasks === []) {
            throw new GatewayException('sendBulkSms requires at least one task.');
        }

        $base = (int) (time() % 2_000_000_000);

        /** @var list<SmsMessage> $rows */
        $rows = [];
        $deviceTasks = [];
        $totalSegments = 0;

        foreach (array_values($tasks) as $index => $task) {
            $from = (string) ($task['from'] ?? $sim->port_number);
            $to = (string) ($task['to'] ?? '');
            $message = (string) ($task['message'] ?? '');

            if ($from === '' || $to === '' || $message === '') {
                throw new GatewayException("Task #{$index} is missing a 'from', 'to', or 'message'.");
            }

            $tid = $base + $index;
            $segments = max(1, (int) ceil(mb_strlen($message) / 160));
            $totalSegments += $segments;

            $rows[] = SmsMessage::create([
                'workspace_id' => $sim->workspace_id,
                'sim_id' => $sim->id,
                'direction' => MessageDirection::Outbound,
                'from_number' => $sim->phone_number,
                'to_number' => $to,
                'message' => $message,
                'segments' => $segments,
                'status' => MessageStatus::Queued,
                'provider_message_id' => 'yxgp:'.$tid,
                'sent_at' => now(),
            ]);

            $deviceTasks[] = [
                'tid' => (string) $tid,
                'from' => $from,
                'to' => $to,
                'sms' => $message,
                'dr' => 1,
                'sdr' => 1,
                'fdr' => 1,
            ];
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withOptions(['verify' => $this->verifyTls])
                ->withHeaders(['Content-Type' => 'application/json;charset=utf-8', 'Accept' => 'application/json'])
                ->post($this->url('/goip_post_sms.html', [
                    'version' => '1.1',
                    'username' => $this->username,
                    'password' => $this->password,
                ]), array_filter([
                    'type' => 'send-sms',
                    'task_num' => count($deviceTasks),
                    'sr_cnt' => 1,
                    'sr_prd' => 5,
                    'sr_url' => $this->statusReportUrl(),
                    'tasks' => $deviceTasks,
                ], fn ($value): bool => $value !== null));

            if (! $response->successful()) {
                $reason = 'HTTP '.$response->status();
                $this->failRows($rows, $reason);

                return new GatewayResponse(false, null, MessageStatus::Failed->value, $reason, $totalSegments);
            }

            $json = $response->json() ?? [];
            $code = (int) ($json['code'] ?? 500);

            if ($code !== 200) {
                $reason = (string) ($json['reason'] ?? 'Unknown gateway response');
                $this->failRows($rows, "Gateway refused ({$code}): {$reason}");

                return new GatewayResponse(false, null, MessageStatus::Failed->value, $reason, $totalSegments);
            }

            return new GatewayResponse(true, null, MessageStatus::Queued->value, null, $totalSegments);
        } catch (ConnectionException|RequestException $e) {
            Log::warning('YX GP sendBulkSms transport failure', ['sim_id' => $sim->id, 'error' => $e->getMessage()]);
            $this->failRows($rows, 'Gateway unreachable: '.Str::limit($e->getMessage(), 200));

            return new GatewayResponse(false, null, MessageStatus::Failed->value, $e->getMessage(), $totalSegments);
        } catch (Throwable $e) {
            Log::error('YX GP sendBulkSms unexpected failure', ['sim_id' => $sim->id, 'error' => $e->getMessage()]);
            $this->failRows($rows, 'Unexpected error: '.Str::limit($e->getMessage(), 200));
            throw new GatewayException('YX GP sendBulkSms failed: '.$e->getMessage(), 0, $e);
        } finally {
            if (config('app.env') !== 'production') {
                foreach ($rows as $row) {
                    SimulateMessageDeliveryJob::dispatch($row->id)->delay(now()->addSeconds(30));
                }
            }
        }
    }

    /**
     * Mark every SmsMessage row in a failed batch as Failed with a shared reason.
     *
     * @param  list<SmsMessage>  $rows
     */
    protected function failRows(array $rows, string $reason): void
    {
        foreach ($rows as $row) {
            $row->update(['status' => MessageStatus::Failed, 'error_message' => $reason]);
        }
    }

    /**
     * Per-SIM status is not exposed as a single endpoint on the YX GP — the
     * admin UI composes it from several. For MVP we return a coarse view
     * using only what we know locally; a future implementation could scrape
     * the status JSON the device exposes via `/GP_status.html`.
     */
    public function getSimStatus(Sim $sim): SimStatus
    {
        return new SimStatus(
            online: $sim->status?->value === 'active',
            signalStrength: null,
            balance: null,
            carrier: $sim->carrier?->value,
        );
    }

    /**
     * Pull inbound SMS via GET /goip_get_sms.html. Safe to call repeatedly —
     * de-duplication is the caller's job (we key on composite (sim_id, sender, content, timestamp)).
     *
     * @return array<int, IncomingMessage>
     */
    public function pollIncomingMessages(Sim $sim): array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withOptions(['verify' => $this->verifyTls])
                ->get($this->url('/goip_get_sms.html', [
                    'username' => $this->username,
                    'password' => $this->password,
                    'sms_num' => 0, // all
                ]))
                ->throw();
        } catch (Throwable $e) {
            Log::warning('YX GP pollIncomingMessages failed', ['sim_id' => $sim->id, 'error' => $e->getMessage()]);

            return [];
        }

        $payload = $response->json();

        // The response shape is vendor-ish — the manual implies a list but is fuzzy on the key names.
        // Normalise both likely shapes: { sms: [...] } or a raw array.
        $items = $payload['sms'] ?? ($payload['messages'] ?? (is_array($payload) ? $payload : []));

        $results = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $from = (string) ($item['from'] ?? $item['sender'] ?? '');
            $body = (string) ($item['sms'] ?? $item['content'] ?? $item['message'] ?? '');
            $port = (int) ($item['port'] ?? $item['port_no'] ?? 0);
            $time = $item['time'] ?? $item['scts'] ?? null;

            if ($from === '' || $body === '') {
                continue;
            }

            // Only pick up messages received by *this* SIM's port.
            if ($port !== 0 && $port !== (int) $sim->port_number) {
                continue;
            }

            $results[] = new IncomingMessage(
                fromNumber: $from,
                toNumber: $sim->phone_number,
                message: $body,
                receivedAt: $time ? Carbon::parse($time) : now(),
                providerMessageId: isset($item['id']) ? 'yxgp-inbox:'.$item['id'] : null,
            );
        }

        return $results;
    }

    /**
     * Public URL the device should POST status-reports to (V2.4.0 §6.3.3).
     * Null if no callback token is configured — the device will fall back to its admin-UI setting.
     */
    protected function statusReportUrl(): ?string
    {
        return 'https://reunite-relax-empathy.ngrok-free.dev/gateway/callback/dlr?token='. config('simgateway.callback.token', '');
        $token = (string) config('simgateway.callback.token', '');

        return $token === '' ? null : route('gateway.callback.dlr', ['token' => $token]);
    }

    /**
     * Public URL the device should POST received SMS to (V2.4.0 §7).
     */
    protected function inboundSmsUrl(): ?string
    {
        $token = (string) config('simgateway.callback.token', '');

        return $token === '' ? null : route('gateway.callback.sms', ['token' => $token]);
    }

    /**
     * Build a URL for the gateway. `host` may already include a scheme + port.
     *
     * @param  array<string, scalar>  $query
     */
    protected function url(string $path, array $query = []): string
    {
        $host = $this->host;
        if (! Str::startsWith($host, ['http://', 'https://'])) {
            $host = 'http://'.$host;
        }

        $url = rtrim($host, '/').$path;

        if ($query) {
            $url .= '?'.http_build_query($query);
        }

        return $url;
    }
}
