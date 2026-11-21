<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Development\RegulatoryBackboneLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

final class RegulatoryLifecycleController extends Controller
{
    public function __invoke(Request $request, RegulatoryBackboneLifecycle $lifecycle): JsonResponse
    {
        $filters = $request->validate([
            'case' => ['sometimes', 'string', 'max:80', 'regex:/\A[A-Z0-9-]+\z/'],
            'jurisdiction' => ['sometimes', 'string', 'max:32', 'regex:/\A[A-Z0-9-]+\z/'],
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
            'as_of' => ['sometimes', 'date_format:Y-m-d'],
            'stage' => ['sometimes', 'string', Rule::in(RegulatoryBackboneLifecycle::STAGE_IDS)],
        ]);
        if (isset($filters['year'])) {
            $filters['year'] = (int) $filters['year'];
        }

        try {
            return response()->json($lifecycle->read($filters))
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'REGULATORY_BACKBONE_SOURCE_UNAVAILABLE',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
