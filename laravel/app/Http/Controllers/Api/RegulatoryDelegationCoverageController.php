<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Regulatory\RegulatoryDelegationCoverage;
use Illuminate\Http\JsonResponse;
use Throwable;

/** Tenant-gated route via canonical /api middleware; read-only QA. */
final class RegulatoryDelegationCoverageController extends Controller
{
    public function __invoke(RegulatoryDelegationCoverage $coverage): JsonResponse
    {
        try {
            return response()->json($coverage->project())
                ->header('Cache-Control', 'private, no-store')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'REGULATORY_DELEGATION_COVERAGE_UNAVAILABLE',
                'production_admission' => 'DENIED',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
