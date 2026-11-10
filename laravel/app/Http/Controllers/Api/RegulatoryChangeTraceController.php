<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanningIntelligence\RegulatoryChainProjector;
use App\Services\PlanningIntelligence\RegulatoryChangeTraceProjector;
use Illuminate\Http\JsonResponse;
use Throwable;

/** Read-only structural change candidates; no legal, numeric, or AI inference. */
final class RegulatoryChangeTraceController extends Controller
{
    public function __invoke(
        RegulatoryChainProjector $source,
        RegulatoryChangeTraceProjector $chronology
    ): JsonResponse {
        try {
            $verified = $source->project(
                (string) config('pi.regulatory.ledger_path'),
                (string) config('pi.regulatory.scope_path')
            );

            return response()->json($chronology->build($verified))
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'REGULATORY_CHANGE_TRACE_UNAVAILABLE',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
