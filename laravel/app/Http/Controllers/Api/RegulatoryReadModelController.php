<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanningIntelligence\RegulatoryChainProjector;
use Illuminate\Http\JsonResponse;
use Throwable;

class RegulatoryReadModelController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(RegulatoryChainProjector $projector): JsonResponse
    {
        try {
            return response()->json($projector->project(
                (string) config('pi.regulatory.ledger_path'),
                (string) config('pi.regulatory.scope_path'),
            ));
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'REJECTED',
                'code' => 'REGULATORY_READ_MODEL_UNAVAILABLE',
            ], 503);
        }
    }
}
