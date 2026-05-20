<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AdminSupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = QueryBuilder::for(SupportTicket::query())
            ->with(['workspace:id,name,slug', 'user:id,name,email'])
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('category'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('reference', 'like', "%{$value}%")
                            ->orWhere('subject', 'like', "%{$value}%")
                            ->orWhere('description', 'like', "%{$value}%")
                            ->orWhereHas('workspace', function ($workspaceQuery) use ($value) {
                                $workspaceQuery->where('name', 'like', "%{$value}%")
                                    ->orWhere('slug', 'like', "%{$value}%");
                            })
                            ->orWhereHas('user', function ($userQuery) use ($value) {
                                $userQuery->where('name', 'like', "%{$value}%")
                                    ->orWhere('email', 'like', "%{$value}%");
                            });
                    });
                }),
            ])
            ->allowedSorts(['created_at', 'status', 'category', 'reference', 'subject'])
            ->defaultSort('-created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('admin/support-tickets/index', [
            'tickets' => $tickets,
            'filters' => [
                'search' => $request->input('filter.search'),
                'status' => $request->input('filter.status'),
                'category' => $request->input('filter.category'),
            ],
            'query' => [
                ...$request->only(['sort', 'per_page', 'page']),
            ],
        ]);
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(SupportTicket::STATUSES)],
        ]);

        $ticket->update([
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('admin.support-tickets.index')
            ->with('success', 'Support ticket updated.');
    }
}
