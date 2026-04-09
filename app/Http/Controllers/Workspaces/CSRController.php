<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Pancake\Models\User as PancakeUser;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CSRController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $employees = QueryBuilder::for(PancakeUser::class)
            ->with('systemUser')
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('pancake_users.name', 'like', "%{$value}%")
                            ->orWhere('pancake_users.email', 'like', "%{$value}%")
                            ->orWhere('pancake_users.phone_number', 'like', "%{$value}%")
                            ->orWhere('pancake_users.status', 'like', "%{$value}%");
                    })->orWhereHas('systemUser', function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['name', 'email', 'phone_number', 'created_at', 'status', 'user_name'])
            ->defaultSort('pancake_users.name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('workspaces/csr/index', [
            'workspace' => $workspace,
            'employees' => $employees,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
            'systemUsers' => User::whereHas('workspaces', fn ($query) => $query->where('workspace_id', $workspace->id))->get()
        ]);
    }

    public function analytics(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        return Inertia::render('workspaces/csr/analytics', [
            'workspace' => $workspace,
        ]);
    }

    public function update(Request $request, Workspace $workspace, PancakeUser $employee)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:ACTIVE,INACTIVE',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $employee->update($validated);

        return redirect()->back()->with('success', 'Employee updated successfully');
    }
}
