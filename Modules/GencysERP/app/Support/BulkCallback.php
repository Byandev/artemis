<?php

namespace Modules\GencysERP\Support;

use Illuminate\Http\Request;
use Modules\GencysERP\Models\GencysSyncRun;

/**
 * The shape every "here is the whole report" callback from n8n shares.
 *
 * Since each sync asks the ERP for one window and gets everything on it back,
 * a callback is one run's entire result rather than one subject's slice. That
 * makes three things the same for all of them — how the list is unwrapped, how
 * the run it belongs to is found, and how a report too big for one POST is
 * credited — so they live here rather than in each controller.
 */
class BulkCallback
{
    /**
     * The entries a callback carries, whichever way they were wrapped.
     *
     * n8n workflows post a bare array as readily as a keyed object, and the key
     * they choose is whatever the node was named, so every shape is read rather
     * than made someone's problem to get right.
     *
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    public static function entries(Request $request, array $keys = ['items', 'data']): array
    {
        foreach ($keys as $key) {
            if (is_array($value = $request->input($key))) {
                return array_values(array_filter($value, 'is_array'));
            }
        }

        // A bare array body is the list itself. It has to be read off the JSON
        // source rather than all(), which merges the query string in and so
        // stops the body looking like a list at all.
        $body = $request->isJson() ? $request->json()->all() : $request->all();

        return array_is_list($body) ? array_values(array_filter($body, 'is_array')) : [];
    }

    /**
     * Credit this callback to the run it belongs to and, unless more is coming,
     * close it. Returns the run, or null when there was none to credit.
     *
     * n8n echoes the run id back as `sync_run_id`, in the body, the query
     * string, or alongside the list it wraps — in which case the caller has
     * already read it and passes it as $runId. Without one we fall back to the
     * workspace's oldest run of this type still in flight, unambiguous now that
     * a batch only ever has one window of a given type out at a time.
     *
     * The counts always go in as a heartbeat first, so a report posted in
     * pieces adds up instead of the last piece overwriting the total; closing
     * then takes the accumulated figures.
     */
    public static function creditRun(
        Request $request,
        int $workspaceId,
        string $syncType,
        int $received,
        int $saved,
        ?string $executionId = null,
        ?int $runId = null,
    ): ?GencysSyncRun {
        $runId ??= SyncCallbackFields::runId($request)
            ?? GencysSyncRun::oldestInFlight($workspaceId, $syncType)?->id;

        if (! $runId) {
            return null;
        }

        $run = GencysSyncRun::heartbeatById($workspaceId, $runId, $received, $saved, $executionId);

        if (self::expectsMore($request)) {
            return $run;
        }

        return GencysSyncRun::finishById($workspaceId, $runId, executionId: $executionId) ?? $run;
    }

    /**
     * Whether n8n says this is one piece of a report still being posted. Send
     * `has_more` (or `final: false`) on every piece but the last.
     */
    public static function expectsMore(Request $request): bool
    {
        if ($request->has('has_more')) {
            return $request->boolean('has_more');
        }

        foreach (['final', 'is_final', 'last_chunk'] as $key) {
            if ($request->has($key)) {
                return ! $request->boolean($key);
            }
        }

        return false;
    }
}
