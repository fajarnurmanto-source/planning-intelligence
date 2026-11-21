<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanningIntelligence\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntitlementController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, EntitlementService $entitlements): JsonResponse
    {
        return response()->json($entitlements->forTenant((string) $request->user()?->tenant_id));
    }
}
