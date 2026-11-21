<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Development\R89SemanticRegulatoryAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/** User-triggered local QA analysis. No guest/foreign tenant, hidden background inference or review mutation. */
final class R89SemanticRegulatoryController extends Controller
{
    public function __invoke(Request $request, R89SemanticRegulatoryAnalysis $analysis): JsonResponse
    {
        $input = $request->validate([
            'consent' => ['required', 'accepted'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'as_of' => ['required', 'date_format:Y-m-d'],
            'code' => ['required', 'string', 'max:40', 'regex:/\A\d{1,2}(?:\.\d{1,4}){1,5}\z/'],
        ]);
        try {
            $payload = $analysis->analyze([
                'year' => (int) $input['year'],
                'as_of' => $input['as_of'],
                'code' => $input['code'],
            ]);

            return response()->json($payload)->header('Cache-Control', 'private, no-store')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'R89_CANONICAL_SOURCE_UNAVAILABLE',
                'production_admission' => 'DENIED',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
