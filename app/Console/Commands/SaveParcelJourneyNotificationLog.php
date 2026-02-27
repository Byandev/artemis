<?php

namespace App\Console\Commands;

use App\Models\ParcelJourney;
use App\Models\ParcelJourneyNotification;
use App\Models\ParcelJourneyNotificationLog;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;

class SaveParcelJourneyNotificationLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'save-parcel-journey-notification-log';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $startDate = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
        $endDate = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');

        $result = DB::table('parcel_journey_notifications as pjn')
            ->join('orders as o', 'o.id', '=', 'pjn.order_id')
            ->select([
                DB::raw('DATE(pjn.created_at) as date'),
                'o.page_id',
                DB::raw('SUM(CASE WHEN pjn.type = "sms" THEN 1 ELSE 0 END) as sms_sent'),
                DB::raw('SUM(CASE WHEN pjn.type = "chat" THEN 1 ELSE 0 END) as chat_sent'),
                DB::raw('COUNT(DISTINCT pjn.order_id) as tracked_orders'),
            ])
            ->where('pjn.status', 'sent')
            ->whereBetween('pjn.created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(pjn.created_at)'), 'o.page_id')
            ->orderBy('date')
            ->orderBy('o.page_id')
            ->get();

        $result->each(function ($item) {
            ParcelJourneyNotificationLog::updateOrCreate([
                'page_id' => $item->page_id,
                'date' => $item->date,
            ], [
                'chat_sent' => $item->chat_sent,
                'sms_sent' => $item->sms_sent,
                'tracked_orders' => $item->tracked_orders,
            ]);
        });

        ParcelJourneyNotification::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'sent')
            ->delete();

        ParcelJourney::doesntHave('notifications')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->delete();
    }
}
