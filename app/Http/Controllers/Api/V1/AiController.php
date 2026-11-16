<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Services\AI\InsightsService;
use App\Services\AI\OrderParser;
use App\Services\Reports\SalesReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    /** "2 kopi o, nasi lemak tambah telur" → cart lines (NOT saved; the client confirms first). */
    public function parseOrder(Request $request, OrderParser $parser): JsonResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:500']]);

        return response()->json($parser->parse(Outlet::current(), $data['text']));
    }

    public function insights(Request $request, SalesReport $report, InsightsService $insights): JsonResponse
    {
        $day = $request->date('date') ?? today();

        return response()->json($insights->summarise($report->daily(Outlet::current(), $day)));
    }
}
