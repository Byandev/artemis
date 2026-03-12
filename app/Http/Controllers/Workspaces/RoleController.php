<?php

namespace App\Http\Controllers\Workspaces;

// You MUST import the base Controller because we moved into a subfolder
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index(Workspace $workspace)
    {

        $users = $workspace->users()
            ->select('users.id', 'users.name', 'users.email', 'users.role')
            ->get();

        return Inertia::render('roles/index', [
            'workspace' => $workspace,
            'users' => $users,
        ]);
    }
}