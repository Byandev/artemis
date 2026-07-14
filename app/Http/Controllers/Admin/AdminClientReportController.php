<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\Reports\ClientReport;
use Inertia\Inertia;

class AdminClientReportController extends Controller
{
    public function show(Workspace $workspace)
    {
        return Inertia::render('admin/workspaces/report', [
            'workspace' => $workspace->only(['id', 'name', 'slug']),
            'report' => (new ClientReport($workspace))->build(),
        ]);
    }
}
