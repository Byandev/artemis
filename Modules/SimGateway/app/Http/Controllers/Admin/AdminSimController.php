<?php

namespace Modules\SimGateway\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\SimGateway\Enums\SimCarrier;
use Modules\SimGateway\Enums\SimStatus;
use Modules\SimGateway\Models\Sim;

/**
 * Super-admin management of the SIM pool: provision SIMs, assign them to a
 * workspace, set the hardware port they occupy, and manage their lifecycle
 * status. Guarded by the ['auth','verified','admin'] group in routes.
 */
class AdminSimController extends Controller
{
    public function index(Request $request): Response
    {
        $sortable = ['phone_number', 'port_number', 'carrier', 'status', 'created_at'];
        $sort = in_array($request->sort, $sortable, true) ? $request->sort : 'created_at';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        $sims = Sim::query()
            ->with('workspace:id,name,slug')
            ->withCount('smsMessages')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('phone_number', 'like', "%{$search}%")
                        ->orWhere('label', 'like', "%{$search}%");
                });
            })
            ->when($request->workspace_id, fn ($q, $id) => $q->where('workspace_id', $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->carrier, fn ($q, $carrier) => $q->where('carrier', $carrier))
            ->orderBy($sort, $direction)
            ->paginate((int) $request->input('per_page', 15))
            ->withQueryString();

        return Inertia::render('admin/sims/index', [
            'sims' => $sims,
            'workspaces' => Workspace::orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statusOptions(),
            'carriers' => $this->carrierOptions(),
            'filters' => $request->only(['search', 'sort', 'direction', 'workspace_id', 'status', 'carrier']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sims/create', [
            'workspaces' => Workspace::orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statusOptions(),
            'carriers' => $this->carrierOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSim($request);

        Sim::create($this->withActivationStamp($data, null));

        return redirect()->route('admin.sims.index')
            ->with('success', 'SIM added.');
    }

    public function edit(Sim $sim): Response
    {
        return Inertia::render('admin/sims/edit', [
            'sim' => $sim->load('workspace:id,name,slug'),
            'workspaces' => Workspace::orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statusOptions(),
            'carriers' => $this->carrierOptions(),
        ]);
    }

    public function update(Request $request, Sim $sim): RedirectResponse
    {
        $data = $this->validateSim($request, $sim);

        $sim->update($this->withActivationStamp($data, $sim));

        return redirect()->route('admin.sims.index')
            ->with('success', 'SIM updated.');
    }

    public function destroy(Sim $sim): RedirectResponse
    {
        $sim->delete();

        return redirect()->route('admin.sims.index')
            ->with('success', 'SIM deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateSim(Request $request, ?Sim $sim = null): array
    {
        return $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'phone_number' => ['required', 'string', 'regex:/^(\+63|0)9\d{9}$/'],
            'carrier' => ['required', Rule::enum(SimCarrier::class)],
            // A hardware port maps to exactly one physical slot, so keep it
            // unique across live (non-deleted) SIMs — inbound routing keys on it.
            'port_number' => [
                'nullable', 'integer', 'min:1', 'max:1024',
                Rule::unique('sim_gateway_sims', 'port_number')->whereNull('deleted_at')->ignore($sim?->id),
            ],
            'label' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(SimStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'phone_number.regex' => 'Please enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).',
            'port_number.unique' => 'That hardware port is already assigned to another SIM.',
        ]);
    }

    /**
     * Stamp activated_at the first time a SIM flips to Active; clear nothing otherwise.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withActivationStamp(array $data, ?Sim $sim): array
    {
        $becomingActive = ($data['status'] ?? null) === SimStatus::Active->value;
        $alreadyStamped = $sim?->activated_at !== null;

        if ($becomingActive && ! $alreadyStamped) {
            $data['activated_at'] = now();
        }

        return $data;
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
