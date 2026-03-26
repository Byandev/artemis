<?php

namespace App\Http\Controllers\API\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $workspace = Workspace::find($request->workspace->id);

        $data = \Cache::remember(json_encode($request->only(['date_range', 'filter', 'metric'])), 5 * 60, function () use ($request, $workspace) {
            return $workspace->metrics($request->array('date_range', []), $request->array('filter', []))
                ->extract($request->array('metric'));
        });

        return response()->json($data);
    }

    public function breakdown(Request $request)
    {
        $workspace = Workspace::find($request->workspace->id);

        $data = $workspace->metrics(
            $request->array('date_range', []),
            $request->array('filter', [])
        )->breakdown(
            $request->input('metric', 'totalSales'),
            $request->input('group', 'daily')
        );

        return response()->json(['data' => $data]);
    }

    public function perPage(Request $request)
    {
        $workspace = Workspace::findOrFail($request->workspace->id);

        $data = $workspace->metrics(
            $request->array('date_range', []),
            $request->array('filter', [])
        )->perPage(
            $request->input('metric', 'totalSales')
        );

        return response()->json(['data' => $data]);
    }

    public function perShop(Request $request)
    {
        $workspace = Workspace::findOrFail($request->workspace->id);

        $data = $workspace->metrics(
            $request->array('date_range', []),
            $request->array('filter', [])
        )->perShop(
            $request->input('metric', 'totalSales')
        );

        return response()->json(['data' => $data]);
    }

    public function perUser(Request $request)
    {
        $workspace = Workspace::findOrFail($request->workspace->id);

        $data = $workspace->metrics(
            $request->array('date_range', []),
            $request->array('filter', [])
        )->perUser(
            $request->input('metric', 'totalSales')
        );

        return response()->json(['data' => $data]);
    }
}
