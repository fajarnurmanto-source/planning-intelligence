<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Regulatory\OfficialNormativeAncestry;
use App\Services\Pi\Regulatory\RegulatoryGenealogyGraph;
use App\Services\Pi\Regulatory\RegulatoryTaxonomyRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** R74 read-only taxonomy and bounded bidirectional evidence navigation. */
final class RegulatoryGenealogyController extends Controller
{
    public function catalogue(RegulatoryTaxonomyRegistry $registry): JsonResponse
    {
        try {
            $data = $registry->load();
            return response()->json([
                'schema' => 'PI_R74_REGULATORY_TAXONOMY_V1',
                'tenant_id' => config('pi.tenant_id'),
                'taxonomy_source' => $data['taxonomy_source'],
                'catalogue_as_of' => $data['catalogue_as_of'],
                'types' => $data['types'],
                'topics' => $data['topics'],
                'instruments' => array_map(static fn (array $item): array => [
                    'id' => $item['id'], 'title' => $item['title'], 'kind' => $item['kind'],
                    'jurisdiction' => $item['jurisdiction'],
                ], $data['instruments']),
                'legal_harmonization' => 'NOT_ASSESSED',
                'review_status' => 'REVIEW_REQUIRED',
                'production_admission' => 'DENIED',
            ])->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);
            return $this->unavailable();
        }
    }

    public function trace(
        Request $request, RegulatoryTaxonomyRegistry $registry, RegulatoryGenealogyGraph $graph
    ): JsonResponse {
        $validated = $request->validate([
            'root' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9_:-]+$/'],
            'direction' => ['nullable', 'in:ancestors,descendants,both'],
            'lens' => ['nullable', 'in:legal,version,thematic,all'],
            'depth' => ['nullable', 'integer', 'between:1,5'],
        ]);
        try {
            $seed = $registry->load();
            // R70R is an optional previously populated cache. Never initiate a live
            // HTTP request or convert its citation candidates into verified ancestry.
            $ancestry = Cache::store((string) config('pi.regulatory_auto_ancestry.cache_store', 'file'))
                ->get(OfficialNormativeAncestry::CACHE_KEY);
            $compiled = $graph->build($seed, is_array($ancestry) ? $ancestry : null);
            if (!isset($compiled['nodes'][$validated['root']])) {
                return response()->json([
                    'schema' => 'PI_R74_BIDIRECTIONAL_GENEALOGY_TRACE_V1',
                    'status' => 'ABSTAIN', 'code' => 'UNKNOWN_REGULATORY_NODE',
                    'result_status' => 'NO_INVENTED_ANCESTRY',
                ], 404)->header('Cache-Control', 'private, no-store');
            }

            return response()->json($graph->traverse(
                $compiled, $validated['root'], $validated['direction'] ?? 'ancestors',
                $validated['lens'] ?? 'legal', (int) ($validated['depth'] ?? 3)
            ))->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);
            return $this->unavailable();
        }
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'status' => 'ABSTAIN',
            'code' => 'REGULATORY_GENEALOGY_SOURCE_UNAVAILABLE',
            'legal_harmonization' => 'NOT_ASSESSED',
            'production_admission' => 'DENIED',
        ], 503)->header('Cache-Control', 'private, no-store');
    }
}
