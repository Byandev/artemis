<?php

namespace Modules\TaskManagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\TaskManagement\Models\Attachment;

/**
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Both URLs are our own endpoints rather than links into the bucket. The
     * bucket is private, so a direct link would have to be signed, and a signed
     * link in a list payload hands out access to every file on the page for as
     * long as the signature lasts. Going through the route means each fetch is
     * authorized on its own.
     *
     * `previewable` comes from the server so the allowlist of content types a
     * browser may render lives in one place. A client that worked it out from
     * `mime_type` would be a second copy of a security decision.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->model_id,
            'name' => $this->name,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'download_url' => route('api.workspaces.task-management.attachments.download', [$request->route('workspace'), $this->resource]),
            'preview_url' => route('api.workspaces.task-management.attachments.preview', [$request->route('workspace'), $this->resource]),
            'previewable' => $this->isPreviewable(),
            'uploaded_by' => $this->uploaded_by,
            'uploader' => new UserSummaryResource($this->whenLoaded('uploader')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
