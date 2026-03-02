<?php

namespace App\Http\Controllers\Workspaces\Botcake;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Inertia\Inertia;

class FlowController extends Controller
{
    public function index(Workspace $workspace)
    {
        return Inertia::render('workspaces/botcake/flows', [
            'workspace' => $workspace,
        ]);
    }
}
