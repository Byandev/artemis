<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreSupportTicketRequest;
use App\Models\SupportTicket;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;

class SupportTicketController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $tickets = QueryBuilder::for(SupportTicket::query())
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->allowedSorts(['created_at', 'status', 'category', 'subject', 'reference'])
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('workspaces/support/index', [
            'workspace' => $workspace,
            'tickets' => $tickets,
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
            ],
        ]);
    }

    public function store(StoreSupportTicketRequest $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize('create', [SupportTicket::class, $workspace]);

        $validated = $request->validated();

        SupportTicket::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'category' => $validated['category'],
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'current_url' => $validated['current_url'] ?? $request->headers->get('referer'),
            'user_agent' => $validated['user_agent'] ?? $request->userAgent(),
            'status' => 'open',
        ]);

        return redirect()
            ->route('support.index', $workspace)
            ->with('success', 'Support request submitted.');
    }

    public function update(Request $request, Workspace $workspace, SupportTicket $ticket)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        if ((int) $ticket->workspace_id !== (int) $workspace->id || (int) $ticket->user_id !== (int) $request->user()->id) {
            abort(403, 'You can only update your own support tickets.');
        }

        $validated = $request->validate([
            'category' => ['required', Rule::in(SupportTicket::CATEGORIES)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $ticket->update($validated);

        return redirect()
            ->route('support.index', $workspace)
            ->with('success', 'Support request updated.');
    }

    public function destroy(Request $request, Workspace $workspace, SupportTicket $ticket)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        if ((int) $ticket->workspace_id !== (int) $workspace->id || (int) $ticket->user_id !== (int) $request->user()->id) {
            abort(403, 'You can only delete your own support tickets.');
        }

        $ticket->delete();

        return redirect()
            ->route('support.index', $workspace)
            ->with('success', 'Support request deleted.');
    }
}
