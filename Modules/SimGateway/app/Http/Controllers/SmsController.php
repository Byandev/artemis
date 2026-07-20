<?php

namespace Modules\SimGateway\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\SimGateway\Enums\MessageDirection;
use Modules\SimGateway\Enums\SimStatus;
use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Models\SmsMessage;
use Modules\SimGateway\Services\Gateway\GatewayInterface;

class SmsController extends Controller
{
    protected function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    public function create(Request $request, Workspace $workspace): Response
    {
        $this->guard($request, $workspace);

        $sims = Sim::where('workspace_id', $workspace->id)
            ->where('status', SimStatus::Active)
            ->get(['id', 'phone_number', 'label', 'carrier']);

        return Inertia::render('workspaces/sms/send', [
            'workspace' => $workspace,
            'sims' => $sims,
        ]);
    }

    public function store(Request $request, Workspace $workspace, GatewayInterface $gateway): RedirectResponse
    {
        $this->guard($request, $workspace);

        $data = $request->validate([
            'sim_id' => ['required', 'integer'],
            'to' => ['required', 'regex:/^(\\+63|0)9\\d{9}$/'],
            'message' => ['required', 'string', 'max:1600'],
        ], [
            'to.regex' => 'Please enter a valid Philippine mobile number.',
        ]);

        $sim = Sim::where('workspace_id', $workspace->id)
            ->where('status', SimStatus::Active)
            ->findOrFail($data['sim_id']);

        $gateway->sendSms($sim, $data['to'], $data['message']);

        return redirect()
            ->route('workspaces.sms.outbox', $workspace->slug)
            ->with('success', 'Message queued — it will appear in Outbox momentarily.');
    }

    public function bulkStore(Request $request, Workspace $workspace, GatewayInterface $gateway): RedirectResponse
    {
        $this->guard($request, $workspace);

        $data = $request->validate([
            'sim_id' => ['required', 'integer'],
            'recipients' => ['required', 'array', 'min:1', 'max:1000'],
            'recipients.*' => ['regex:/^(\\+63|0)9\\d{9}$/'],
            'message' => ['required', 'string', 'max:1600'],
        ]);

        $sim = Sim::where('workspace_id', $workspace->id)
            ->where('status', SimStatus::Active)
            ->findOrFail($data['sim_id']);

        foreach ($data['recipients'] as $to) {
            $gateway->sendSms($sim, $to, $data['message']);
        }

        return redirect()
            ->route('workspaces.sms.outbox', $workspace->slug)
            ->with('success', count($data['recipients']).' messages queued.');
    }

    public function inbox(Request $request, Workspace $workspace): Response
    {
        $this->guard($request, $workspace);

        $messages = SmsMessage::where('workspace_id', $workspace->id)
            ->where('direction', MessageDirection::Inbound)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('workspaces/sms/inbox', [
            'workspace' => $workspace,
            'messages' => $messages,
        ]);
    }

    public function outbox(Request $request, Workspace $workspace): Response
    {
        $this->guard($request, $workspace);

        $messages = SmsMessage::where('workspace_id', $workspace->id)
            ->where('direction', MessageDirection::Outbound)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('workspaces/sms/outbox', [
            'workspace' => $workspace,
            'messages' => $messages,
        ]);
    }
}
