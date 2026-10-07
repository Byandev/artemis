<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * Sets the customer / rider status on public RMO page rows — one at a time, or
 * in bulk behind the workspace's "bulk status update" switch.
 */
class RmoSubStatusController extends Controller
{
    private const CLOSED_MESSAGE = "Status can only be updated for today's orders, or yesterday's delivered orders. Turn on \"Edit Previous Days\" to open up earlier dates.";

    public function update(Workspace $workspace, $id, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:cx,rider'],
            'status_id' => ['nullable', 'integer'],
        ]);

        $orderForDelivery = OrderForDelivery::where('workspace_id', $workspace->id)->find($id);

        if (! $orderForDelivery) {
            return redirect()->back()->with('error', 'Order not found.');
        }

        if (! $this->canEdit($workspace, $orderForDelivery)) {
            return redirect()->back()->with('error', self::CLOSED_MESSAGE);
        }

        if ($data['status_id'] !== null && ! $this->findStatus($workspace, $data['type'], $data['status_id'])) {
            return redirect()->back()->with('error', 'That status no longer exists.');
        }

        $orderForDelivery->update([$data['type'].'_status_id' => $data['status_id']]);

        $label = $data['type'] === 'cx' ? 'Customer' : 'Rider';

        return redirect()->back()->with('success', "{$label} status updated successfully");
    }

    /**
     * Orders outside the editable window are skipped rather than failing the
     * whole batch.
     */
    public function bulkUpdate(Workspace $workspace, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'type' => ['required', 'in:cx,rider'],
            'status_id' => ['nullable', 'integer'],
        ]);

        if (! $workspace->rmoBulkStatusUpdateEnabled()) {
            return redirect()->back()->with('error', 'Bulk status update is turned off for this workspace.');
        }

        $status = $data['status_id'] !== null
            ? $this->findStatus($workspace, $data['type'], $data['status_id'])
            : null;

        if ($data['status_id'] !== null && ! $status) {
            return redirect()->back()->with('error', 'That status no longer exists.');
        }

        $editableIds = OrderForDelivery::whereIn('id', $data['ids'])
            ->where('workspace_id', $workspace->id)
            ->get(['id', 'delivery_date', 'parcel_status'])
            ->filter(fn (OrderForDelivery $order) => $this->canEdit($workspace, $order))
            ->pluck('id');

        if ($editableIds->isEmpty()) {
            return redirect()->back()->with('error', self::CLOSED_MESSAGE);
        }

        OrderForDelivery::whereIn('id', $editableIds)->update([$data['type'].'_status_id' => $data['status_id']]);

        $label = $data['type'] === 'cx' ? 'customer' : 'rider';
        $message = $status
            ? "Set the {$label} status of {$editableIds->count()} order(s) to {$status->name}."
            : "Cleared the {$label} status of {$editableIds->count()} order(s).";

        $skipped = count($data['ids']) - $editableIds->count();
        if ($skipped > 0) {
            $message .= " {$skipped} order(s) skipped — outside the editable date range.";
        }

        return redirect()->back()->with('success', $message);
    }

    private function findStatus(Workspace $workspace, string $type, int $id)
    {
        $relation = $type === 'cx' ? 'rmoCxStatuses' : 'rmoRiderStatuses';

        return $workspace->{$relation}()->find($id);
    }

    /**
     * Same window as the main status (ForDeliveryController::publicUpdateStatus):
     * today, yesterday's delivered/returning parcels, or any past day when the
     * workspace turned on "Edit Previous Days".
     */
    private function canEdit(Workspace $workspace, OrderForDelivery $orderForDelivery): bool
    {
        $deliveryDate = $orderForDelivery->delivery_date
            ? Carbon::parse($orderForDelivery->delivery_date)
            : null;

        $isToday = $deliveryDate?->isToday() ?? false;
        $isDeliveredYesterday = ($deliveryDate?->isYesterday() ?? false)
            && in_array(strtolower((string) $orderForDelivery->parcel_status), ['delivered', 'returning'], true);
        $isPastDay = $deliveryDate?->lt(today()) ?? false;

        return $isToday || $isDeliveredYesterday || ($isPastDay && $workspace->rmoEditPreviousDayEnabled());
    }
}
