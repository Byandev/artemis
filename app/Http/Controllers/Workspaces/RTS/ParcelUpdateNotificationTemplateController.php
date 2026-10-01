<?php

namespace App\Http\Controllers\Workspaces\RTS;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ParcelJourneyNotificationTemplate;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ParcelUpdateNotificationTemplateController extends Controller
{
    use AuthorizesRequests;

    public function index(Workspace $workspace, Request $request)
    {
        $this->authorize(Permission::ViewParcelJourneyTemplates->value, $workspace);

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
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        // Neither the stat cards nor the per-shop table are computed here any
        // more: each fetches its own figures from ParcelJourneyStatsController,
        // so the page renders — and sorts, and pages — without waiting on the
        // aggregates. The window is echoed back only to seed the date picker.
        return Inertia::render('workspaces/rts/parcel-update-notification-templates', [
            'workspace' => $workspace,
            'templates' => $templates,
            'query' => [
                'start_date' => $request->input('start_date', now()->startOfMonth()->toDateString()),
                'end_date' => $request->input('end_date', now()->endOfMonth()->toDateString()),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace, ParcelJourneyNotificationTemplate $template)
    {
        $this->authorize(Permission::ManageParcelJourneyTemplates->value, $workspace);

        abort_unless((int) $template->workspace_id === (int) $workspace->id, 404);

        $data = $request->validate([
            'message' => 'required|string',
            'is_enabled' => 'required|boolean',
        ]);

        $template->update($data);

        return redirect()->back()->with('success', 'Parcel journey notification updated successfully.');
    }
}
