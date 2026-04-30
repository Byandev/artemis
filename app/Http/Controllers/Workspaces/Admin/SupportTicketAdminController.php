<?php

namespace App\Http\Controllers\Workspaces\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SupportTicketAdminController extends Controller
{
    use AuthorizesRequests;

    private const STATUS_TRANSITIONS = [
        'open' => ['open', 'in_progress', 'resolved', 'closed'],
        'in_progress' => ['in_progress', 'resolved', 'closed'],
        'resolved' => ['resolved', 'closed'],
        'closed' => ['closed'],
    ];

    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize('viewAny', [SupportTicket::class, $workspace]);

        $tickets = QueryBuilder::for(SupportTicket::query())
            ->where('workspace_id', $workspace->id)
            ->with('user')
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('category'),
            ])
            ->allowedSorts(['created_at', 'status', 'category', 'reference', 'subject'])
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/admin/support-tickets/index', [
            'workspace' => $workspace,
            'tickets' => $tickets,
            'filters' => [
                'status' => $request->input('filter.status'),
                'category' => $request->input('filter.category'),
            ],
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace, SupportTicket $ticket)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        if ($ticket->workspace_id !== $workspace->id) {
            abort(403);
        }

        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'status' => ['required', Rule::in(SupportTicket::STATUSES)],
        ]);

        $allowed = self::STATUS_TRANSITIONS[$ticket->status] ?? [];

        if (! in_array($validated['status'], $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'That status transition is not allowed.',
            ]);
        }

        $ticket->update([
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('admin.support-tickets.index', $workspace)
            ->with('success', 'Support ticket updated.');
    }
}
