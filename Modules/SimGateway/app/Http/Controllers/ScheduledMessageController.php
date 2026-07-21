<?php

namespace Modules\SimGateway\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\SimGateway\Enums\ScheduledMessageStatus;
use Modules\SimGateway\Enums\SimStatus;
use Modules\SimGateway\Models\ScheduledMessage;
use Modules\SimGateway\Models\Sim;

class ScheduledMessageController extends Controller
{
    protected function guard(Request $request, Workspace $workspace): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }
    }

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->guard($request, $workspace);

        $scheduled = ScheduledMessage::where('workspace_id', $workspace->id)
            ->where('status', ScheduledMessageStatus::Pending)
            ->orderBy('scheduled_at')
            ->get();

        $sims = Sim::where('workspace_id', $workspace->id)
            ->where('status', SimStatus::Active)
            ->get(['id', 'phone_number', 'label', 'carrier']);

        return Inertia::render('workspaces/sms/scheduled', [
            'workspace' => $workspace,
            'scheduled' => $scheduled,
            'sims' => $sims,
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->guard($request, $workspace);

        $data = $request->validate([
            'sim_id' => ['required', 'integer'],
            'to_number' => ['required', 'regex:/^(\\+63|0)9\\d{9}$/'],
            'message' => ['required', 'string', 'max:1600'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        // Ensure the chosen SIM belongs to this workspace.
        Sim::where('workspace_id', $workspace->id)->findOrFail($data['sim_id']);

        ScheduledMessage::create([
            'workspace_id' => $workspace->id,
            'sim_id' => $data['sim_id'],
            'to_number' => $data['to_number'],
            'message' => $data['message'],
            'scheduled_at' => $data['scheduled_at'],
            'status' => ScheduledMessageStatus::Pending,
        ]);

        return back()->with('success', 'Message scheduled.');
    }

    public function destroy(Request $request, Workspace $workspace, ScheduledMessage $message): RedirectResponse
    {
        $this->guard($request, $workspace);
        abort_unless($message->workspace_id === $workspace->id, 404);

        $message->update(['status' => ScheduledMessageStatus::Cancelled]);

        return back()->with('success', 'Scheduled message cancelled.');
    }
}
