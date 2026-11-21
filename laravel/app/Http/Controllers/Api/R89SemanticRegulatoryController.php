<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Development\R89SemanticRegulatoryAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Authenticated tenant-scoped preview then user-approved cloud analysis.
 * A GET never sends any data to a provider.
 */
final class R89SemanticRegulatoryController extends Controller
{
    public function preview(Request $request, R89SemanticRegulatoryAnalysis $analysis): JsonResponse
    {
        $filters = $this->filters($request);
        try {
            return response()->json($analysis->preview($filters))
                ->header('Cache-Control', 'private, no-store')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable $error) {
            report($error);

            return $this->unavailable();
        }
    }

    public function analyze(Request $request, R89SemanticRegulatoryAnalysis $analysis): JsonResponse
    {
        $validated = $request->validate([
            'consent' => ['required', 'accepted'],
            'approved_cloud_metadata' => ['required', 'accepted'],
            'approved_provider_charges' => ['required', 'accepted'],
            'approved_context_sha256' => ['required', 'string', 'regex:/\A[A-Fa-f0-9]{64}\z/D'],
            'provider' => ['required', 'in:gemini,deepseek,openai,groq,openrouter'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'as_of' => ['required', 'date_format:Y-m-d'],
            'code' => ['required', 'string', 'max:40', 'regex:/\A\d{1,2}(?:\.\d{1,4}){1,5}\z/'],
        ]);
        try {
            $result = $analysis->analyze(
                $this->normalize($validated),
                $validated['provider'],
                (string) $request->user()->tenant_id,
                $validated['approved_context_sha256'],
            );
            $status = ($result['semantic_reasoning']['status'] ?? '') === 'ABSTAIN_PREVIEW_STALE' ? 409 : 200;

            return response()->json($result, $status)
                ->header('Cache-Control', 'private, no-store')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable $error) {
            report($error);

            return $this->unavailable();
        }
    }

    /** @return array<string,mixed> */
    private function filters(Request $request): array
    {
        return $this->normalize($request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'as_of' => ['required', 'date_format:Y-m-d'],
            'code' => ['required', 'string', 'max:40', 'regex:/\A\d{1,2}(?:\.\d{1,4}){1,5}\z/'],
        ]));
    }

    /** @param array<string,mixed> $validated
     *  @return array<string,mixed>
     */
    private function normalize(array $validated): array
    {
        return [
            'year' => (int) $validated['year'],
            'as_of' => $validated['as_of'],
            'code' => $validated['code'],
        ];
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'status' => 'ABSTAIN',
            'code' => 'R89_CANONICAL_SOURCE_UNAVAILABLE',
            'production_admission' => 'DENIED',
        ], 503)->header('Cache-Control', 'private, no-store');
    }
}
