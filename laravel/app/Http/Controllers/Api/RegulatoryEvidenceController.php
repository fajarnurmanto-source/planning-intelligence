<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlanningIntelligence\RegulatoryEvidenceResolver;
use Illuminate\Http\JsonResponse;
use Throwable;

final class RegulatoryEvidenceController extends Controller
{
    public function __invoke(RegulatoryEvidenceResolver $resolver, string $sourceId, string $excerptSha256): JsonResponse
    {
        try {
            $evidence = $resolver->resolve(
                (string) config('pi.regulatory.ledger_path'),
                (string) config('pi.regulatory.scope_path'),
                $sourceId,
                $excerptSha256,
            );
            if ($evidence === null) {
                return response()->json(['status' => 'NOT_FOUND', 'code' => 'EVIDENCE_ANCHOR_NOT_FOUND'], 404);
            }

            return response()->json($evidence)->header('Cache-Control', 'no-store, private');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'REJECTED',
                'code' => 'REGULATORY_EVIDENCE_UNAVAILABLE',
            ], 503);
        }
    }
}
