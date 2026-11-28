<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Development\OfficialSourceMetadataCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * R100 read-only, local and tenant-authenticated official document metadata.
 * Never fetch remote source bytes or infer figures/authority from an index.
 */
final class OfficialSourceMetadataController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'lane' => ['sometimes', 'string', Rule::in(['PLANNING', 'SECTOR', 'FISCAL', 'ACCOUNTABILITY'])],
        ]);
        try {
            $result = (new OfficialSourceMetadataCatalogue(
                (string) config('pi.official_sources.registry_path')
            ))->read($filters['lane'] ?? null);

            $result['tenant_id'] = $request->user()->tenant_id;
            return response()->json($result)->header('Cache-Control', 'no-store, private');
        } catch (Throwable) {
            return response()->json([
                'schema' => 'PI_R100_OFFICIAL_SOURCE_METADATA_READ_MODEL_V1',
                'status' => 'ABSTAIN',
                'code' => 'OFFICIAL_SOURCE_METADATA_UNAVAILABLE',
                'production_admission' => 'DENIED',
            ], 503)->header('Cache-Control', 'no-store, private');
        }
    }
}
