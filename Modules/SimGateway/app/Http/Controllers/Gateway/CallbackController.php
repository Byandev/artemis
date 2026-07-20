<?php

namespace Modules\SimGateway\Http\Controllers\Gateway;

use App\Http\Controllers\Controller;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Modules\SimGateway\Enums\MessageDirection;
use Modules\SimGateway\Enums\MessageStatus;
use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Models\SmsMessage;

/**
 * Callbacks pushed by the YX GP hardware gateway.
 *
 * Wire matches V2.4.0 of the YX HTTP API spec:
 *   - §7.2 "Receive SMS"   → POST {"type":"recv-sms",   "sms_num":N, "sms":[[dr,port,ts,from,to,base64]…]}
 *   - §6.3.3 "Status report" → POST {"type":"status-report","rpt_num":N,"rpts":[{tid,sending,sent,failed,unsent,sdr,fdr}…]}
 *
 * The device pushes here when its `sms_url` / `sr_url` are pointed at us
 * (we set these per-task in YxGpGateway, so no device-side config is required).
 */
class CallbackController extends Controller
{
    /**
     * Inbound SMS push (V2.4.0 §7).
     * Each entry in `sms[]` is a positional array: [dr_flag, port, ts, sender, recipient, content].
     * `content` is base64-encoded UTF-8 for ordinary messages; for delivery reports it's "code scts".
     */
    public function inboundSms(Request $request): JsonResponse
    {
        $payload = $request->json()->all() ?: $request->all();
        $items = $payload['sms'] ?? [];
        if (! is_array($items) || $items === []) {
            // Tolerate legacy form-encoded callbacks for backward compat.
            return $this->inboundSmsLegacy($request);
        }

        $stored = 0;
        foreach ($items as $row) {
            if (! is_array($row) || count($row) < 6) {
                continue;
            }

            [$drFlag, $portRaw, $tsRaw, $from, $to, $contentRaw] = $row;

            // Delivery-report-flavoured rows are forwarded SMS dropped on the inbox endpoint
            // — ignore them here; the status-report endpoint is the source of truth for DLR.
            if ((int) $drFlag === 1) {
                continue;
            }

            $port = $this->extractPort((string) $portRaw);
            $sim = $this->findSim($port, (string) $to);
            if (! $sim) {
                Log::warning('gateway.inbound_sms: no matching SIM', ['port' => $port, 'to' => $to]);

                continue;
            }

            $decoded = base64_decode((string) $contentRaw, true);
            $message = $decoded !== false ? $decoded : (string) $contentRaw;
            $receivedAt = $this->parseTime($tsRaw);

            $existing = SmsMessage::where('sim_id', $sim->id)
                ->where('from_number', (string) $from)
                ->where('message', $message)
                ->where('sent_at', $receivedAt)
                ->first();

            if ($existing) {
                continue;
            }

            SmsMessage::create([
                'workspace_id' => $sim->workspace_id,
                'sim_id' => $sim->id,
                'direction' => MessageDirection::Inbound,
                'from_number' => (string) $from,
                'to_number' => $sim->phone_number,
                'message' => $message,
                'status' => MessageStatus::Received,
                'segments' => max(1, (int) ceil(mb_strlen($message) / 160)),
                'sent_at' => $receivedAt,
                'delivered_at' => $receivedAt,
                'provider_message_id' => 'yxgp-push:'.uniqid('sms_', true),
            ]);

            $stored++;
        }

        return response()->json(['ok' => true, 'stored' => $stored]);
    }

    /**
     * Delivery / failure report push (V2.4.0 §6.3.3).
     * `rpts[]` items carry per-task counters; we update the matching SmsMessage by tid.
     */
    public function deliveryReport(Request $request): JsonResponse
    {
        $payload = $request->json()->all() ?: $request->all();
        $rpts = $payload['rpts'] ?? null;

        if (! is_array($rpts)) {
            return $this->deliveryReportLegacy($request);
        }

        $updated = 0;
        foreach ($rpts as $rpt) {
            if (! is_array($rpt) || ! isset($rpt['tid'])) {
                continue;
            }

            $tid = $rpt['tid'];
            $sms = SmsMessage::where('provider_message_id', 'yxgp:'.$tid)->first();
            if (! $sms) {
                Log::info('gateway.dlr: unmatched tid', ['tid' => $tid]);

                continue;
            }

            $sent = (int) ($rpt['sent'] ?? 0);
            $failed = (int) ($rpt['failed'] ?? 0);
            $sending = (int) ($rpt['sending'] ?? 0);

            $updates = [];
            if ($failed > 0 && $sent === 0) {
                $updates['status'] = MessageStatus::Failed;
                $updates['error_message'] = $this->extractFailureReason($rpt['fdr'] ?? null);
            } elseif ($sent > 0) {
                $updates['status'] = MessageStatus::Sent;
                $updates['sent_at'] = $sms->sent_at ?? $this->extractFirstSdrTime($rpt['sdr'] ?? null);
            } elseif ($sending > 0) {
                $updates['status'] = MessageStatus::Sending;
            }

            if ($updates !== []) {
                $sms->update($updates);
                $updated++;
            }
        }

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    /**
     * Best-effort match: by hardware port first (most reliable), then by recipient MSISDN.
     */
    protected function findSim(int $port, string $receiver): ?Sim
    {
        if ($port > 0) {
            $sim = Sim::where('port_number', $port)->first();
            if ($sim) {
                return $sim;
            }
        }
        if ($receiver !== '') {
            return Sim::where('phone_number', $receiver)->first();
        }

        return null;
    }

    /**
     * Convert "1.01" / "2.03" port-slot strings to the integer port number we store.
     */
    protected function extractPort(string $raw): int
    {
        if ($raw === '') {
            return 0;
        }
        if (str_contains($raw, '.')) {
            return (int) explode('.', $raw)[0];
        }

        return (int) $raw;
    }

    /**
     * fdr[i] shape: [recipient_index, number, port, ts, progress_reason, carrier_reason].
     */
    protected function extractFailureReason(mixed $fdr): string
    {
        if (! is_array($fdr) || $fdr === []) {
            return 'Send failed.';
        }
        $row = $fdr[0];
        if (! is_array($row)) {
            return 'Send failed.';
        }
        $progress = (string) ($row[4] ?? '');
        $carrier = (string) ($row[5] ?? '');

        return trim($carrier !== '' ? $progress.' — '.$carrier : ($progress ?: 'Send failed.'));
    }

    protected function extractFirstSdrTime(mixed $sdr): DateTimeInterface
    {
        if (is_array($sdr) && isset($sdr[0][3])) {
            return $this->parseTime($sdr[0][3]);
        }

        return now();
    }

    protected function parseTime(mixed $raw): DateTimeInterface
    {
        if (! $raw) {
            return now();
        }
        if (is_numeric($raw)) {
            // Spec §7 uses int timestamps; treat as Unix seconds (UTC).
            return Date::createFromTimestamp((int) $raw);
        }
        try {
            return Date::parse((string) $raw);
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * Backwards-compatible legacy handler for older device firmwares that
     * push form-encoded fields (sender / content / port) instead of JSON.
     */
    protected function inboundSmsLegacy(Request $request): JsonResponse
    {
        $from = (string) ($request->input('sender') ?? $request->input('from') ?? $request->input('phone') ?? '');
        $message = (string) ($request->input('content') ?? $request->input('sms') ?? $request->input('message') ?? '');
        if ($from === '' || $message === '') {
            return response()->json(['error' => 'Missing sender or content'], 422);
        }

        $port = (int) ($request->input('port') ?? $request->input('port_no') ?? 0);
        $receiver = (string) ($request->input('receiver') ?? $request->input('to') ?? '');
        $sim = $this->findSim($port, $receiver);

        if (! $sim) {
            Log::warning('gateway.inbound_sms (legacy): no matching SIM', compact('port', 'receiver'));

            return response()->json(['error' => 'No matching SIM for this port/receiver'], 404);
        }

        $receivedAt = $this->parseTime($request->input('time') ?? $request->input('scts'));

        $sms = SmsMessage::create([
            'workspace_id' => $sim->workspace_id,
            'sim_id' => $sim->id,
            'direction' => MessageDirection::Inbound,
            'from_number' => $from,
            'to_number' => $sim->phone_number,
            'message' => $message,
            'status' => MessageStatus::Received,
            'segments' => max(1, (int) ceil(mb_strlen($message) / 160)),
            'sent_at' => $receivedAt,
            'delivered_at' => $receivedAt,
            'provider_message_id' => 'yxgp-push:'.$request->input('id', uniqid('sms_', true)),
        ]);

        return response()->json(['ok' => true, 'id' => $sms->id]);
    }

    /**
     * Backwards-compatible legacy handler for older firmwares pushing a flat
     * {tid, status, time} body instead of the V2.4.0 status-report shape.
     */
    protected function deliveryReportLegacy(Request $request): JsonResponse
    {
        $tid = $request->input('tid') ?? $request->input('task_id') ?? $request->input('id');
        $statusRaw = strtolower((string) ($request->input('status') ?? $request->input('state') ?? ''));

        if (! $tid) {
            return response()->json(['error' => 'Missing tid'], 422);
        }

        $sms = SmsMessage::where('provider_message_id', 'yxgp:'.$tid)->first();
        if (! $sms) {
            return response()->json(['error' => 'Unknown tid'], 404);
        }

        $newStatus = match (true) {
            str_contains($statusRaw, 'ok'), str_contains($statusRaw, 'deliver') => MessageStatus::Delivered,
            str_contains($statusRaw, 'sent') => MessageStatus::Sent,
            str_contains($statusRaw, 'fail'), str_contains($statusRaw, 'error'), str_contains($statusRaw, 'reject') => MessageStatus::Failed,
            default => MessageStatus::Sent,
        };

        $updates = ['status' => $newStatus];
        $now = $this->parseTime($request->input('time'));

        if ($newStatus === MessageStatus::Delivered) {
            $updates['delivered_at'] = $now;
            if (! $sms->sent_at) {
                $updates['sent_at'] = $now;
            }
        }
        if ($newStatus === MessageStatus::Sent && ! $sms->sent_at) {
            $updates['sent_at'] = $now;
        }
        if ($newStatus === MessageStatus::Failed) {
            $updates['error_message'] = (string) $request->input('reason', $statusRaw);
        }

        $sms->update($updates);

        return response()->json(['ok' => true]);
    }
}
