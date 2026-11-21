<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Regulatory\OfficialRegulationDiscovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** On-demand official source discovery; never authorizes legal or budget decisions. */
final class RegulatoryDiscoveryController extends Controller
{
    public function __invoke(Request $request, OfficialRegulationDiscovery $discovery): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:100'],
            'source' => ['nullable', 'in:jdih_bpk'],
        ]);
        $query = trim($validated['q']);
        if (mb_strlen($query) < 3) {
            return response()->json(['code' => 'DISCOVERY_QUERY_TOO_SHORT'], 422)
                ->header('Cache-Control', 'private, no-store');
        }

        try {
            return response()->json($discovery->search($query, $validated['source'] ?? 'jdih_bpk'))
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'schema' => 'PI_R70O_OFFICIAL_DISCOVERY_V1',
                'code' => 'OFFICIAL_SOURCE_UNAVAILABLE',
                'status' => 'ABSTAIN',
                'items' => [],
                'legal_status' => 'NOT_VERIFIED',
                'amendment_census' => 'NOT_EXHAUSTIVELY_VERIFIED',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
