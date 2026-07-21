<?php

namespace Modules\SimGateway\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\SimGateway\Enums\MessageDirection;
use Modules\SimGateway\Enums\MessageStatus;
use Modules\SimGateway\Enums\SimCarrier;
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

        $sortable = ['created_at', 'to_number', 'status', 'sent_at'];
        $sort = in_array($request->sort, $sortable, true) ? $request->sort : null;
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        $messages = SmsMessage::where('workspace_id', $workspace->id)
            ->where('direction', MessageDirection::Outbound)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('to_number', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when(
                $sort,
                fn ($q) => $q->orderBy($sort, $direction),
                fn ($q) => $q->latest(),
            )
            ->paginate((int) $request->input('per_page', 25))
            ->withQueryString()
            ->through(fn (SmsMessage $m) => [
                'id' => $m->id,
                'to_number' => $m->to_number,
                'message' => $m->message,
                'segments' => $m->segments,
                'status' => $m->status->value,
                'sent_at' => $m->sent_at,
                'created_at' => $m->created_at,
            ]);

        return Inertia::render('workspaces/sms/outbox', [
            'workspace' => $workspace,
            'messages' => $messages,
            'statuses' => $this->messageStatusOptions(),
            'filters' => $request->only(['search', 'sort', 'direction', 'status']),
        ]);
    }

    /**
     * Outbound-relevant message statuses for the Outbox filter (excludes Received).
     *
     * @return array<int, array{value: string, label: string}>
     */
    protected function messageStatusOptions(): array
    {
        return array_values(array_map(
            fn (MessageStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
            array_filter(
                MessageStatus::cases(),
                fn (MessageStatus $s): bool => $s !== MessageStatus::Received,
            ),
        ));
    }

    /**
     * Read-only, paginated list of the workspace's SIMs (admin provisions them)
     * with message counts and last activity, searchable/sortable/filterable.
     */
    public function sims(Request $request, Workspace $workspace): Response
    {
        $this->guard($request, $workspace);

        $sortable = ['phone_number', 'carrier', 'port_number', 'status', 'inbound_count', 'outbound_count', 'last_activity_at'];
        $sort = in_array($request->sort, $sortable, true) ? $request->sort : null;
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        $sims = Sim::where('workspace_id', $workspace->id)
            ->withCount([
                'smsMessages as inbound_count' => fn ($q) => $q->where('direction', MessageDirection::Inbound),
                'smsMessages as outbound_count' => fn ($q) => $q->where('direction', MessageDirection::Outbound),
            ])
            ->withMax('smsMessages as last_activity_at', 'created_at')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('phone_number', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%");
                });
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->carrier, fn ($q, $carrier) => $q->where('carrier', $carrier))
            ->when(
                $sort,
                fn ($q) => $q->orderBy($sort, $direction),
                // Default: active SIMs first, then by number.
                fn ($q) => $q
                    ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [SimStatus::Active->value])
                    ->orderBy('phone_number'),
            )
            ->paginate((int) $request->input('per_page', 15))
            ->withQueryString()
            ->through(fn (Sim $sim) => [
                'id' => $sim->id,
                'phone_number' => $sim->phone_number,
                'label' => $sim->label,
                'carrier' => $sim->carrier->value,
                'port_number' => $sim->port_number,
                'status' => $sim->status->value,
                'inbound_count' => (int) $sim->inbound_count,
                'outbound_count' => (int) $sim->outbound_count,
                'last_activity_at' => $sim->last_activity_at,
            ]);

        return Inertia::render('workspaces/sms/sims', [
            'workspace' => $workspace,
            'sims' => $sims,
            'statuses' => $this->statusOptions(),
            'carriers' => $this->carrierOptions(),
            'filters' => $request->only(['search', 'sort', 'direction', 'status', 'carrier']),
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return array_map(
            fn (SimStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
            SimStatus::cases()
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function carrierOptions(): array
    {
        return array_map(
            fn (SimCarrier $c): array => ['value' => $c->value, 'label' => $c->label()],
            SimCarrier::cases()
        );
    }
}
