<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Services\Reports\SalesReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function daily(Request $request, SalesReport $report): JsonResponse
    {
        return response()->json($report->daily(Outlet::current(), $request->date('date') ?? today()));
    }
}
