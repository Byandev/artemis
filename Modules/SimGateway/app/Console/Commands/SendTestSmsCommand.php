<?php

namespace Modules\SimGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fire a single SMS straight at the real YX GP hardware device to prove the
 * end-to-end provider path works.
 *
 * This talks to the device directly from config (simgateway.yxgp.*) and does
 * NOT touch the database — no SIM lookup, no SmsMessage rows. It mirrors the
 * wire format in YxGpGateway::sendSms (POST /goip_post_sms.html). A real
 * handset receives the message, so it confirms before firing unless --force.
 */
class SendTestSmsCommand extends Command
{
    protected $signature = 'sms:test
        {to : Recipient MSISDN, e.g. 09171234567 or +639171234567}
        {--port= : Hardware SIM slot to send from (the device task "from"; required)}
        {--message= : Message body (defaults to a timestamped test message)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Send a test SMS directly through the real YX GP device (no DB writes).';

    public function handle(): int
    {
        $host = (string) config('simgateway.yxgp.host', '');
        if ($host === '') {
            $this->error('SIMGATEWAY_YXGP_HOST is not set. Configure the real device first:');
            $this->line('  SIMGATEWAY_YXGP_HOST, SIMGATEWAY_YXGP_USERNAME, SIMGATEWAY_YXGP_PASSWORD');

            return self::FAILURE;
        }

        if (config('simgateway.driver') !== 'yxgp') {
            $this->warn('Note: simgateway.driver is "'.config('simgateway.driver').'"; this command hits the YX GP device regardless.');
        }

        $to = (string) $this->argument('to');
        $port = (string) ($this->option('port') ?? '');
        $message = (string) ($this->option('message')
            ?: 'Artemis SMS gateway test — '.now()->toDateTimeString());

        if ($port === '') {
            $this->error('--port is required (the device SIM slot to send from), e.g. --port=1.');

            return self::FAILURE;
        }

        if (! preg_match('/^(\+63|0)9\d{9}$/', $to)) {
            $this->warn("Heads up: \"{$to}\" is not a standard PH mobile number (09XXXXXXXXX / +639XXXXXXXXX).");
            if (! $this->option('force') && ! $this->confirm('Send to it anyway?', false)) {
                return self::FAILURE;
            }
        }

        $tid = (string) (int) (time() % 2_000_000_000);
        $url = $this->url($host, '/goip_post_sms.html', [
            'version' => '1.1',
            'username' => (string) config('simgateway.yxgp.username'),
            'password' => (string) config('simgateway.yxgp.password'),
        ]);
        $payload = [
            'type' => 'send-sms',
            'task_num' => 1,
            'sr_cnt' => 1,
            'sr_prd' => 5,
            'sr_url' => 'https://reunite-relax-empathy.ngrok-free.dev/gateway/callback/dlr?token='.config('simgateway.callback.token', ''),
            'tasks' => [[
                'tid' => $tid,
                'from' => $port,
                'to' => $to,
                'sms' => $message,
                'dr' => 1,
                'sdr' => 1,
                'fdr' => 1,
                'tmo' => 60,
            ]],
        ];

        $this->newLine();
        $this->line('  <fg=gray>Device:</>   '.preg_replace('/(password=)[^&]*/', '$1***', $url));
        $this->line("  <fg=gray>From:</>     port {$port}");
        $this->line("  <fg=gray>To:</>       {$to}");
        $this->line("  <fg=gray>Message:</>  {$message}");
        $this->line("  <fg=gray>tid:</>      {$tid}");
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('This sends a REAL SMS through the device. Proceed?', false)) {
            $this->comment('Aborted.');

            return self::FAILURE;
        }

        try {
            $response = Http::timeout((int) config('simgateway.yxgp.timeout_seconds', 10))
                ->withOptions(['verify' => (bool) config('simgateway.yxgp.verify_tls', true)])
                ->withHeaders([
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Accept' => 'application/json',
                    'X-Api-Key' => config('simgateway.yxgp.auth_token'),
                ])
                ->post($url, $payload);
        } catch (ConnectionException|RequestException $e) {
            $this->error('Device unreachable: '.$this->maskPassword($e->getMessage()));

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Request failed: '.$this->maskPassword($e->getMessage()));

            return self::FAILURE;
        }

        $this->line('  <fg=gray>HTTP:</>     '.$response->status());
        $this->line('  <fg=gray>Body:</>     '.Str::limit($response->body(), 500));
        $this->newLine();

        $json = $response->json() ?? [];
        $code = (int) ($json['code'] ?? ($response->successful() ? 0 : 500));

        if ($response->successful() && $code === 200) {
            $this->info("Accepted by device (code 200). tid={$tid}.");
            $this->comment('Delivery status arrives later via the device\'s status-report POST to /gateway/callback/dlr.');

            return self::SUCCESS;
        }

        $this->error('Device did not accept the task'.($code ? " (code {$code})" : '').': '.($json['reason'] ?? 'see body above'));

        return self::FAILURE;
    }

    /**
     * Build a device URL. `host` may already include a scheme + port.
     *
     * @param  array<string, scalar>  $query
     */
    protected function url(string $host, string $path, array $query = []): string
    {
        if (! Str::startsWith($host, ['http://', 'https://'])) {
            $host = 'http://'.$host;
        }

        $url = rtrim($host, '/').$path;

        return $query ? $url.'?'.http_build_query($query) : $url;
    }

    /**
     * Redact the `password=…` query param so the device password never lands
     * in the terminal or logs (Guzzle echoes the full URL in its exceptions).
     */
    protected function maskPassword(string $text): string
    {
        return (string) preg_replace('/(password=)[^&\s]*/', '$1***', $text);
    }
}
