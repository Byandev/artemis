<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->leftJoin('users', 'pancake_users.user_id', '=', 'users.id')
            ->select([
                'pancake_users.*',
                'users.name as user_name',
            ])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('pancake_users.name', 'like', "%{$value}%")
                            ->orWhere('pancake_users.email', 'like', "%{$value}%")
                            ->orWhere('pancake_users.phone_number', 'like', "%{$value}%")
                            ->orWhere('pancake_users.status', 'like', "%{$value}%")
                            ->orWhere('users.name', 'like', "%{$value}%");
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
            'systemUsers' => User::all(['id', 'name']),
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

    public function update(Request $request, Workspace $workspace, $employee)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:ACTIVE,INACTIVE',
            'user_id' => 'nullable|exists:users,id',
        ]);

        DB::table('pancake_users')
            ->where('id', $employee)
            ->update([
                'status' => strtoupper($validated['status']),
                'user_id' => $validated['user_id'] ?: null,
                'updated_at' => now(),
            ]);

        return redirect()->back()->with('success', 'Employee updated successfully');
    }
}
