<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Development\RegulatoryPlanningCrosswalk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class RegulatoryPlanningCrosswalkController extends Controller
{
    public function __invoke(Request $request, RegulatoryPlanningCrosswalk $crosswalk): JsonResponse
    {
        $filters = $request->validate([
            'year' => ['sometimes', 'integer', 'between:2000,2100'],
            'as_of' => ['sometimes', 'date_format:Y-m-d'],
            'code' => ['sometimes', 'string', 'max:40', 'regex:/\A\d{1,2}(?:\.\d{1,4}){1,5}\z/'],
        ]);
        if (isset($filters['year'])) {
            $filters['year'] = (int) $filters['year'];
        }
        try {
            return response()->json($crosswalk->read($filters))->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json(['status' => 'ABSTAIN', 'code' => 'CROSSWALK_SOURCE_UNAVAILABLE'], 503)
                ->header('Cache-Control', 'private, no-store');
        }
    }
}
