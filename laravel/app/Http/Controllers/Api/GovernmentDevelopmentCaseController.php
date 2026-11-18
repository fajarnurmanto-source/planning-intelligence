<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Development\DevelopmentCaseGraph;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class GovernmentDevelopmentCaseController extends Controller
{
    public function __invoke(Request $request, DevelopmentCaseGraph $graph): JsonResponse
    {
        $filters = $request->validate([
            'jurisdiction' => ['sometimes', 'string', 'max:32'],
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
            'topic' => ['sometimes', 'string', 'max:64'],
            'case' => ['sometimes', 'string', 'max:80'],
        ]);
        if (isset($filters['year'])) {
            $filters['year'] = (int) $filters['year'];
        }

        try {
            return response()->json($graph->read($filters))
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'DEVELOPMENT_CASE_SOURCE_UNAVAILABLE',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }
}
