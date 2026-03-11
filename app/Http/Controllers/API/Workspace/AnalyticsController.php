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

        //        $data = \Cache::remember(json_encode($request->only(['date_range', 'filter'])), 5 * 60, function () use ($request, $workspace) {
        //            return $workspace->metrics($request->array('date_range', []), $request->array('filter', []))
        //                ->extract([
        // //                    'rtsRate',
        // //                    'aov',
        //                    'totalSales',
        //                    'totalOrders',
        // //                    'repeatOrderRatio',
        // //                    'timeToFirstOrder',
        // //                    'avgLifetimeValue',
        // //                    'avgDeliveryDays',
        // //                    'avgShippedOutDays'
        //                ]);
        //        });

        $data = $workspace->metrics($request->array('date_range', []), $request->array('filter', []))
            ->extract([
                'rtsRate',
                'aov',
                'totalSales',
                'totalOrders',
                'repeatOrderRatio',
                'timeToFirstOrder',
                'avgLifetimeValue',
                'avgDeliveryDays',
                'avgShippedOutDays',
            ]);

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
}
