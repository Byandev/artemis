<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Http\Controllers\Controller;
use App\Models\ParcelJourneyNotification;
use App\Models\ParcelJourneyNotificationLog;
use App\Models\ParcelJourneyNotificationTemplate;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
            ->paginate(15)
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

        return Inertia::render('workspaces/rts/parcel-update-notification-templates', [
            'workspace' => $workspace,
            'templates' => $templates,
            'analytics' => [
                'tracked_orders' => $trackedOrders,
                'sms_sent' => $smsSent,
                'chat_sent' => $chatSent,
                'total_sent' => $totalSent,
            ],
            'query' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
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
