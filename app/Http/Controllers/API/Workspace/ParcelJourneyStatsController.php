<?php

namespace App\Http\Controllers\API\Workspace;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ParcelJourneyNotification;
use App\Models\ParcelJourneyNotificationLog;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The RTS parcel journey page's figures — the four stat cards and the per-shop
 * breakdown below them. Each resolves on its own request so it can load,
 * skeleton, sort, and page independently instead of holding up the page render
 * behind the slowest query on it.
 *
 * Every figure is the sum of two sources, the same way the page has always
 * counted them: the nightly rollup in parcel_journey_notification_logs for days
 * already closed out, plus the live parcel_journey_notifications rows for the
 * days that have not been rolled up yet.
 */
class ParcelJourneyStatsController extends Controller
{
    use AuthorizesRequests;

    /** Orders that had at least one journey notification in the window. */
    public function trackedOrders(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize(Permission::ViewParcelJourneyTemplates->value, $workspace);

        [$startDate, $endDate] = $this->range($request);

        $value = (int) $this->logs($workspace, $startDate, $endDate)->sum('tracked_orders')
            + $this->notifications($workspace, $startDate, $endDate)
                ->distinct('order_id')
                ->count('order_id');

        return response()->json(['value' => $value]);
    }

    /** SMS that actually left — sent or delivered, not queued or failed. */
    public function smsSent(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize(Permission::ViewParcelJourneyTemplates->value, $workspace);

        [$startDate, $endDate] = $this->range($request);

        return response()->json(['value' => $this->sentCount($workspace, $startDate, $endDate, 'sms')]);
    }

    /** Chat messages that actually left, counted like SMS. */
    public function chatSent(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize(Permission::ViewParcelJourneyTemplates->value, $workspace);

        [$startDate, $endDate] = $this->range($request);

        return response()->json(['value' => $this->sentCount($workspace, $startDate, $endDate, 'chat')]);
    }

    /** SMS plus chat. Recomputed here so the card never waits on its siblings. */
    public function totalSent(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize(Permission::ViewParcelJourneyTemplates->value, $workspace);

        [$startDate, $endDate] = $this->range($request);

        $value = $this->sentCount($workspace, $startDate, $endDate, 'sms')
            + $this->sentCount($workspace, $startDate, $endDate, 'chat');

        return response()->json(['value' => $value]);
    }

    /**
     * The per-shop breakdown behind the cards, paginated and sortable. Same
     * two sources as the cards, joined per shop: only shops that have started
     * a parcel journey in the window are listed. Team-scoped, so a scoped user
     * sees only the shops their team can see.
     */
    public function shops(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize(Permission::ViewParcelJourneyTemplates->value, $workspace);

        [$startDate, $endDate] = $this->range($request);

        $logsAgg = DB::table('parcel_journey_notification_logs as pjnl')
            ->whereBetween('pjnl.date', [$startDate, $endDate])
            ->selectRaw('pjnl.shop_id, MIN(pjnl.date) as first_date, SUM(pjnl.tracked_orders) as tracked_orders, SUM(pjnl.sms_sent) as sms_sent, SUM(pjnl.chat_sent) as chat_sent')
            ->groupBy('pjnl.shop_id');

        $notifAgg = DB::table('parcel_journey_notifications as pjn')
            ->join('pancake_orders as po', 'po.id', '=', 'pjn.order_id')
            ->whereBetween('pjn.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->whereIn('pjn.status', ['sent', 'delivered'])
            ->selectRaw('po.shop_id, MIN(DATE(pjn.created_at)) as first_date, COUNT(DISTINCT pjn.order_id) as tracked_orders, SUM(pjn.type = "sms") as sms_sent, SUM(pjn.type = "chat") as chat_sent')
            ->groupBy('po.shop_id');

        $shopQuery = Shop::where('shops.workspace_id', $workspace->id)
            ->visibleTo($request->user(), $workspace)
            ->leftJoinSub($logsAgg, 'logs_agg', 'logs_agg.shop_id', '=', 'shops.id')
            ->leftJoinSub($notifAgg, 'notif_agg', 'notif_agg.shop_id', '=', 'shops.id')
            ->selectRaw('
                shops.id,
                shops.name as shop_name,
                CASE
                    WHEN logs_agg.first_date IS NOT NULL AND notif_agg.first_date IS NOT NULL
                        THEN LEAST(logs_agg.first_date, notif_agg.first_date)
                    WHEN logs_agg.first_date IS NOT NULL THEN logs_agg.first_date
                    ELSE notif_agg.first_date
                END as parcel_journey_started,
                COALESCE(logs_agg.tracked_orders, 0) + COALESCE(notif_agg.tracked_orders, 0) as tracked_orders,
                COALESCE(logs_agg.sms_sent, 0) + COALESCE(notif_agg.sms_sent, 0) as sms_sent,
                COALESCE(logs_agg.chat_sent, 0) + COALESCE(notif_agg.chat_sent, 0) as chat_sent
            ')
            ->havingRaw('parcel_journey_started IS NOT NULL OR tracked_orders > 0');

        $shopStats = QueryBuilder::for($shopQuery)
            ->allowedSorts([
                AllowedSort::field('shop_name'),
                AllowedSort::field('parcel_journey_started'),
                AllowedSort::field('tracked_orders'),
                AllowedSort::field('sms_sent'),
                AllowedSort::field('chat_sent'),
            ])
            ->defaultSort('-parcel_journey_started')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return response()->json($shopStats);
    }

    /**
     * The date window the cards report on. Defaults to the current month, which
     * is what the page's date picker opens on.
     */
    private function range(Request $request): array
    {
        return [
            $request->input('start_date', now()->startOfMonth()->toDateString()),
            $request->input('end_date', now()->endOfMonth()->toDateString()),
        ];
    }

    /** Rolled-up daily totals for the workspace's shops. */
    private function logs(Workspace $workspace, string $startDate, string $endDate): Builder
    {
        return ParcelJourneyNotificationLog::whereHas('shop', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        })->whereBetween('date', [$startDate, $endDate]);
    }

    /** Individual notifications not yet folded into the rollup. */
    private function notifications(Workspace $workspace, string $startDate, string $endDate): Builder
    {
        return ParcelJourneyNotification::whereHas('order', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        })->whereBetween('created_at', [$startDate, $endDate.' 23:59:59']);
    }

    /** Rollup column plus live rows for one channel. */
    private function sentCount(Workspace $workspace, string $startDate, string $endDate, string $type): int
    {
        return (int) $this->logs($workspace, $startDate, $endDate)->sum("{$type}_sent")
            + $this->notifications($workspace, $startDate, $endDate)
                ->where('type', $type)
                ->whereIn('status', ['sent', 'delivered'])
                ->count();
    }
}
