<?php

namespace Modules\GencysERP\Support;

use Illuminate\Http\Request;

/**
 * Pulls the bookkeeping fields n8n echoes back out of a callback.
 *
 * Every Gencys callback carries the same two: the sync run it belongs to, and
 * the n8n execution that produced it. They arrive in slightly different shapes
 * per flow — sometimes on each entry of a list, sometimes at the top level of
 * the request, and with whatever casing the workflow author used — so the
 * tolerance lives here rather than being re-invented in each controller.
 */
class SyncCallbackFields
{
    /** @var array<int, string> */
    private const RUN_ID_KEYS = ['sync_run_id', 'syncRunId'];

    /**
     * Where an execution id might be, in order of preference. Dotted paths cover
     * the wrappers n8n's Respond-to-Webhook node tends to put around a payload.
     *
     * @var array<int, string>
     */
    private const EXECUTION_ID_KEYS = [
        'n8n_execution_id',
        'execution_id',
        'executionId',
        'n8nExecutionId',
        'data.n8n_execution_id',
        'data.execution_id',
        'data.executionId',
        'body.executionId',
        'body.execution_id',
    ];

    /** The run this entry belongs to, falling back to a request-level field. */
    public static function runId(array|Request $entry, ?Request $request = null): ?int
    {
        $value = self::first($entry, self::RUN_ID_KEYS) ?? self::first($request, self::RUN_ID_KEYS);

        return ($value === null || $value === '') ? null : (int) $value;
    }

    /**
     * The n8n execution behind this entry.
     *
     * A workflow usually posts one execution id for the whole call, so an entry
     * without one falls back to the request level.
     */
    public static function executionId(array|Request $entry, ?Request $request = null): ?string
    {
        $value = self::first($entry, self::EXECUTION_ID_KEYS) ?? self::first($request, self::EXECUTION_ID_KEYS);

        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }

        // Truncated rather than rejected: a too-long id is still worth keeping
        // the front of, and this is a tracing aid, not a key.
        return mb_substr((string) $value, 0, 64);
    }

    /**
     * Pull an execution id out of whatever n8n answered our outbound call with.
     *
     * n8n reports the execution it started in the webhook response, so a run can
     * be stamped the moment it goes out rather than waiting for a callback that
     * may never come — which is exactly when you most want to look it up.
     */
    public static function executionIdFromResponse(mixed $body, array $headers = []): ?string
    {
        if (is_array($body)) {
            if ($id = self::executionId($body)) {
                return $id;
            }
        }

        // Some setups surface it as a header instead of in the body.
        foreach (['x-n8n-execution-id', 'x-execution-id'] as $header) {
            $value = $headers[$header][0] ?? $headers[$header] ?? null;

            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, 64);
            }
        }

        return null;
    }

    /** First present value among $keys, from either an array entry or a request. */
    private static function first(array|Request|null $source, array $keys): mixed
    {
        if ($source === null) {
            return null;
        }

        foreach ($keys as $key) {
            // data_get so the dotted paths above reach into nested payloads.
            $value = $source instanceof Request ? $source->input($key) : data_get($source, $key);

            if ($value !== null && $value !== '' && ! is_array($value)) {
                return $value;
            }
        }

        return null;
    }
}
