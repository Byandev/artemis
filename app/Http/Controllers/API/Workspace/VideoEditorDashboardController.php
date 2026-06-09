<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\VideoEditorDashboardRequest;
use App\Models\Workspace;
use App\Services\VideoEditorDashboard;
use Illuminate\Http\JsonResponse;

/**
 * Per-statistic endpoints for the video-editor dashboard. Each section is
 * resolved independently so the frontend can load it on its own and show a
 * skeleton while waiting. Authorization, validation, and filter parsing are
 * handled by VideoEditorDashboardRequest.
 */
class VideoEditorDashboardController extends Controller
{
    public function __construct(private readonly VideoEditorDashboard $dashboard) {}

    public function totalCreatives(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->totalCreatives($workspace, $request->filters())
        );
    }

    public function awaitingReview(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->awaitingReview($workspace, $request->filters())
        );
    }

    public function needsRevision(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->needsRevision($workspace, $request->filters())
        );
    }

    public function approved(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->approved($workspace, $request->filters())
        );
    }

    public function ads(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->ads($workspace, $request->filters())
        );
    }

    public function pipeline(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->pipeline($workspace, $request->filters())
        );
    }

    public function revisionList(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->revisionList($workspace, $request->filters())
        );
    }

    public function waitingList(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->waitingList($workspace, $request->filters())
        );
    }

    public function throughput(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->throughput($workspace, $request->filters())
        );
    }

    public function leaderboard(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->leaderboard($workspace, $request->filters())
        );
    }

    public function recentActivity(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->recentActivity($workspace, $request->filters())
        );
    }

    public function calendar(VideoEditorDashboardRequest $request, Workspace $workspace): JsonResponse
    {
        return response()->json(
            $this->dashboard->calendar($workspace, $request->filters())
        );
    }
}
