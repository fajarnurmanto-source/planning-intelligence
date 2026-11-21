<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Regulatory\OfficialNormativeAncestry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Ancestry is refreshed server-side automatically when the read model is opened;
 * it can also be refreshed by the Laravel scheduler, without per-law user input.
 */
final class AutomatedAncestryController extends Controller
{
    public function __invoke(OfficialNormativeAncestry $ancestry): JsonResponse
    {
        try {
            if (!config('pi.regulatory_auto_ancestry.enabled', true)) {
                throw new RuntimeException('Official ancestry source disabled.');
            }

            $result = Cache::store((string) config('pi.regulatory_auto_ancestry.cache_store', 'file'))
                ->remember(
                    OfficialNormativeAncestry::CACHE_KEY,
                    now()->addHours(12),
                    fn (): array => $ancestry->trace()
                );

            if (!is_array($result) || ($result['schema'] ?? null) !== 'PI_R70R_AUTOMATED_ANCESTRY_V1'
                || ($result['tenant_id'] ?? null) !== config('pi.tenant_id')
                || ($result['result_status'] ?? null) !== 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY') {
                throw new RuntimeException('Automated ancestry cache contract is invalid.');
            }

            return response()->json($result)->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'AUTOMATED_OFFICIAL_ANCESTRY_UNAVAILABLE',
                'result_status' => 'SOURCE_UNAVAILABLE_NO_INVENTED_ANCESTRY',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
