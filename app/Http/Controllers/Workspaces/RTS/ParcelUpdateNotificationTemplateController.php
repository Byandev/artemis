<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\ParcelJourneyNotification;
use App\Models\ParcelJourneyNotificationLog;
use App\Models\ParcelJourneyNotificationTemplate;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ParcelUpdateNotificationTemplateController extends Controller
{
    public function index(Workspace $workspace, Request $request)
    {
        if ($workspace->parcelJourneyNotificationTemplates()->count() === 0) {
            ParcelJourneyNotificationTemplate::upsert([
                [
                    'workspace_id' => $workspace->id,
                    'type' => 'chat',
                    'activity' => 'departure',
                    'receiver' => 'customer',
                    'message' => "{{customer_name}}, update lang po sa parcel nyo from JNT.\n\nAs of {{date}}, papunta na po ito sa {{next_location}}.\nDadaan muna ito sa warehouse na ito bilang bahagi ng ruta papunta sa inyong address: {{shipping_address}}.\n\nMaraming salamat sa tiwala!\n\n{{page_name}}",
                ],
                [
                    'workspace_id' => $workspace->id,
                    'type' => 'chat',
                    'activity' => 'arrival',
                    'receiver' => 'customer',
                    'message' => "{{customer_name}}, update lang po sa parcel nyo from JNT.\n\nAs of {{date}}, nakarating na po ito sa {{current_location}}.\nDadaan muna ito sa warehouse na ito bilang bahagi ng ruta papunta sa inyong address: {{shipping_address}}.\n\nMaraming salamat sa tiwala!\n\n{{page_name}}",
                ],
                [
                    'workspace_id' => $workspace->id,
                    'type' => 'chat',
                    'activity' => 'for-delivery',
                    'receiver' => 'customer',
                    'message' => "{{customer_name}}, Padating na po ang order nyo mula sa {{page_name}}. Siguraduhing matatawagan po ang cp nyo ng rider. Paki-ready nalang po ng pang bayad.\n Parcel Tracking No. {{tracking_code}}\n Rider Name: {{rider_name}}\nRider No: {{rider_mobile}}",
                ],
                [
                    'workspace_id' => $workspace->id,
                    'type' => 'sms',
                    'activity' => 'departure',
                    'receiver' => 'customer',
                    'message' => "{{customer_name}}, update lang po sa parcel nyo from JNT.\n\nAs of {{date}}, papunta na po ito sa {{next_location}}.\nDadaan muna ito sa warehouse na ito bilang bahagi ng ruta papunta sa inyong address: {{shipping_address}}.\n\nMaraming salamat sa tiwala!\n\n{{page_name}}",
                ],
                [
                    'workspace_id' => $workspace->id,
                    'type' => 'sms',
                    'activity' => 'arrival',
                    'receiver' => 'customer',
                    'message' => "{{customer_name}, update lang po sa parcel nyo from JNT.\n\nAs of {{date}}, nakarating na po ito sa {{current_location}}.\nDadaan muna ito sa warehouse na ito bilang bahagi ng ruta papunta sa inyong address: {{shipping_address}}.\n\nMaraming salamat sa tiwala!\n\n{{page_name}}",
                ],
                [
                    'workspace_id' => $workspace->id,
                    'type' => 'sms',
                    'activity' => 'for-delivery',
                    'receiver' => 'customer',
                    'message' => "{{customer_name}}, Padating na po ang order nyo mula sa {{page_name}}. Siguraduhing matatawagan po ang cp nyo ng rider. Paki-ready nalang po ng pang bayad.\n Parcel Tracking No. {{tracking_code}}\n Rider Name: {{rider_name}}\nRider No: {{rider_mobile}}",
                ],
                [
                    'workspace_id' => $workspace->id,
                    'type' => 'sms',
                    'activity' => 'for-delivery',
                    'receiver' => 'rider',
                    'message' => "Boss {{rider_name}}, Ito po ang seller ng parcel na may tracking no. {{tracking_code}}.\nPakiusap po, ingatan at siguraduhing maideliver agad ito kay {{customer_name}}.\nMatagal na pong hinihintay ito at kailangan na kailangan na po talaga ngayon.\n\nNabanggit din po ng customer na mahina ang signal sa kanilang lugar, kaya kung hindi po matawagan, pakideliver na lang po direkta — siguradong tatanggapin daw po nila.\nNakahanda na rin po ang bayad.\n\n",
                ],
            ], [
                'workspace_id', 'type', 'activity', 'receiver',
            ], ['message']);
        }

        $templates = ParcelJourneyNotificationTemplate::where('workspace_id', $workspace->id)
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $logs = ParcelJourneyNotificationLog::whereHas('page', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        })->whereBetween('date', [$startDate, $endDate]);

        $logsTrackedOrders = (clone $logs)->sum('tracked_orders');
        $logsSmsSent = (clone $logs)->sum('sms_sent');
        $logsChatSent = (clone $logs)->sum('chat_sent');

        $notifBase = ParcelJourneyNotification::whereHas('order', function ($q) use ($workspace) {
            $q->where('workspace_id', $workspace->id);
        })->whereBetween('created_at', [$startDate, $endDate.' 23:59:59']);

        $notifTrackedOrders = (clone $notifBase)->distinct('order_id')->count('order_id');
        $notifSmsSent = (clone $notifBase)->where('type', 'sms')->whereIn('status', ['sent', 'delivered'])->count();
        $notifChatSent = (clone $notifBase)->where('type', 'chat')->whereIn('status', ['sent', 'delivered'])->count();

        $trackedOrders = $logsTrackedOrders + $notifTrackedOrders;
        $smsSent = $logsSmsSent + $notifSmsSent;
        $chatSent = $logsChatSent + $notifChatSent;
        $totalSent = $smsSent + $chatSent;

        $logsAgg = DB::table('parcel_journey_notification_logs as pjnl')
            ->whereBetween('pjnl.date', [$startDate, $endDate])
            ->selectRaw('pjnl.page_id, MIN(pjnl.date) as first_date, SUM(pjnl.tracked_orders) as tracked_orders, SUM(pjnl.sms_sent) as sms_sent, SUM(pjnl.chat_sent) as chat_sent')
            ->groupBy('pjnl.page_id');

        $notifAgg = DB::table('parcel_journey_notifications as pjn')
            ->join('pancake_orders as po', 'po.id', '=', 'pjn.order_id')
            ->whereBetween('pjn.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->whereIn('pjn.status', ['sent', 'delivered'])
            ->selectRaw('po.page_id, MIN(DATE(pjn.created_at)) as first_date, COUNT(DISTINCT pjn.order_id) as tracked_orders, SUM(pjn.type = "sms") as sms_sent, SUM(pjn.type = "chat") as chat_sent')
            ->groupBy('po.page_id');

        $rtsAgg = DB::table('parcel_journeys as pj')
            ->join('pancake_orders as po', 'po.id', '=', 'pj.order_id')
            ->where('po.workspace_id', $workspace->id)
            ->whereBetween('pj.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->whereIn('po.status', [3, 4, 5])
            ->selectRaw('
                po.page_id,
                ROUND(
                    SUM(CASE WHEN po.status IN (4,5) THEN po.final_amount ELSE 0 END)
                    / NULLIF(SUM(CASE WHEN po.status IN (3,4,5) THEN po.final_amount ELSE 0 END), 0)
                    * 100,
                2) as rts_rate
            ')
            ->groupBy('po.page_id');

        $pageQuery = Page::where('pages.workspace_id', $workspace->id)
            ->leftJoinSub($logsAgg, 'logs_agg', 'logs_agg.page_id', '=', 'pages.id')
            ->leftJoinSub($notifAgg, 'notif_agg', 'notif_agg.page_id', '=', 'pages.id')
            ->leftJoinSub($rtsAgg, 'rts_agg', 'rts_agg.page_id', '=', 'pages.id')
            ->selectRaw('
                pages.id,
                pages.name as page_name,
                CASE
                    WHEN logs_agg.first_date IS NOT NULL AND notif_agg.first_date IS NOT NULL
                        THEN LEAST(logs_agg.first_date, notif_agg.first_date)
                    WHEN logs_agg.first_date IS NOT NULL THEN logs_agg.first_date
                    ELSE notif_agg.first_date
                END as parcel_journey_started,
                COALESCE(logs_agg.tracked_orders, 0) + COALESCE(notif_agg.tracked_orders, 0) as tracked_orders,
                COALESCE(logs_agg.sms_sent, 0) + COALESCE(notif_agg.sms_sent, 0) as sms_sent,
                COALESCE(logs_agg.chat_sent, 0) + COALESCE(notif_agg.chat_sent, 0) as chat_sent,
                COALESCE(rts_agg.rts_rate, 0) as rts_rate
            ')
            ->havingRaw('parcel_journey_started IS NOT NULL OR tracked_orders > 0');

        $pageStats = QueryBuilder::for($pageQuery)
            ->allowedSorts([
                AllowedSort::field('page_name'),
                AllowedSort::field('parcel_journey_started'),
                AllowedSort::field('tracked_orders'),
                AllowedSort::field('sms_sent'),
                AllowedSort::field('chat_sent'),
                AllowedSort::field('rts_rate'),
            ])
            ->defaultSort('-parcel_journey_started')
            ->paginate(
                perPage: $request->integer('per_page_stats', 15),
                pageName: 'stats_page',
            )
            ->withQueryString();

        return Inertia::render('workspaces/rts/parcel-update-notification-templates', [
            'workspace'  => $workspace,
            'templates'  => $templates,
            'pageStats'  => $pageStats,
            'analytics'  => [
                'tracked_orders' => $trackedOrders,
                'sms_sent'       => $smsSent,
                'chat_sent'      => $chatSent,
                'total_sent'     => $totalSent,
            ],
            'query' => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'sort'       => $request->input('sort'),
                'stats_page' => $request->integer('stats_page', 1),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace, ParcelJourneyNotificationTemplate $template)
    {
        $data = $request->validate([
            'message' => 'required|string',
        ]);

        $template->update($data);

        return redirect()->back();
    }
}
