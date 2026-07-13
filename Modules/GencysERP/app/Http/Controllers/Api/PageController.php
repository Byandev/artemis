<?php

namespace Modules\GencysERP\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\GencysERP\Models\Page;
use Modules\GencysERP\Support\InternResolver;

/**
 * Callback for the n8n pages sync. n8n posts { data: { workspace_id, api_key,
 * pages: [...] } } — the workspace is resolved from the api_key we sent in the
 * webhook, and its pages are upserted (keyed on Gencys' page id).
 */
class PageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $workspaceId = $request->input('workspace_id');
        $rawKey = $request->input('api_key');
        $pages = $request->input('pages', []);

        $apiKey = $rawKey
            ? WorkspaceApiKey::findByRawKey($rawKey)
            : null;

        if (! $apiKey || (int) $apiKey->workspace_id !== (int) $workspaceId) {
            return response()->json([
                'message' => 'Invalid api_key for workspace.',
            ], 401);
        }

        $apiKey->update([
            'last_used_at' => now(),
        ]);

        $workspace = $apiKey->workspace;
        $interns = new InternResolver($workspace->id);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($pages as $page) {

            $pageId = (int) ($page['page_id'] ?? $page['pageId'] ?? $page['id'] ?? 0);

            if ($pageId <= 0) {
                $skipped++;

                continue;
            }

            $internAndBrand = trim(
                $page['intern_and_brand']
                ?? $page['intern_brands']
                ?? $page['internAndBrand']
                ?? ''
            );

            $dateCreated = null;

            if (! empty($page['date_created'])) {
                try {
                    $dateCreated = Carbon::createFromFormat(
                        'd/m/Y H:i:s',
                        $page['date_created']
                    );
                } catch (\Throwable $e) {
                    try {
                        $dateCreated = Carbon::parse($page['date_created']);
                    } catch (\Throwable $e) {
                        $dateCreated = null;
                    }
                }
            }

            $record = Page::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'page_id' => $pageId,
                ],
                [
                    'fb_page_id' => $page['fb_page_id'] ?? null,
                    'date_created' => $dateCreated,
                    'name' => trim($page['name'] ?? ''),
                    'profile_url' => trim($page['profile_url'] ?? ''),
                    'owner' => trim($page['owner'] ?? ''),
                    'intern_and_brand' => $internAndBrand,
                    'gencys_intern_id' => $interns->resolve($internAndBrand),
                    'status' => $page['status'] ?? '',
                    'platform' => trim($page['platform'] ?? ''),
                ]
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    /** ERP date formats vary; an unparseable value is stored as null, not an error. */
    private function date(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function intOrNull(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
