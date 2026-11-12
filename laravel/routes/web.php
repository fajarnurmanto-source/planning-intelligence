<?php

use App\Http\Controllers\Api\AutomatedAncestryController;
use App\Http\Controllers\Api\AiProviderStatusController;
use App\Http\Controllers\Api\AiEvidenceContextController;
use App\Http\Controllers\Api\AiReviewBriefController;
use App\Http\Controllers\Api\AiLocalInferenceController;
use App\Http\Controllers\Api\AiCloudInferenceController;
use App\Http\Controllers\Api\AiProviderSettingsController;
use App\Http\Controllers\Api\EntitlementController;
use App\Http\Controllers\Api\NormativeRootController;
use App\Http\Controllers\Api\NormativePlanningReadinessController;
use App\Http\Controllers\Api\PlanningLineageController;
use App\Http\Controllers\Api\PlanningProgramCandidateController;
use App\Http\Controllers\Api\RegulatoryEvidenceController;
use App\Http\Controllers\Api\RegulatoryDiscoveryController;
use App\Http\Controllers\Api\RegulatoryReadModelController;
use App\Http\Controllers\Api\RegulatoryLegacyProjectionController;
use App\Http\Controllers\Api\RegulatoryLegalTraceController;
use App\Http\Controllers\Api\RegulatorySnapshotController;
use App\Http\Controllers\Api\RegulatoryChangeTraceController;
use App\Http\Controllers\Api\RegulatoryGenealogyController;
use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/index.html');
});

Route::prefix('api')->group(function (): void {
    Route::get('/auth/setup-status', [SessionController::class, 'setupStatus'])
        ->name('auth.setup-status');
    Route::post('/auth/login', [SessionController::class, 'store'])
        ->middleware('throttle:5,1')->name('auth.login');

    Route::middleware(['auth', 'pi.tenant'])->group(function (): void {
        Route::get('/auth/session', [SessionController::class, 'show'])->name('auth.session');
        Route::get('/ai-r69a/status', AiProviderStatusController::class)
            ->name('pi.ai.provider-status');
        Route::get('/ai-r69a/provider-settings', [AiProviderSettingsController::class, 'index'])
            ->name('pi.ai.provider-settings');
        Route::put('/ai-r69a/provider-settings/{provider}', [AiProviderSettingsController::class, 'save'])
            ->middleware('throttle:5,1')->name('pi.ai.provider-settings.save');
        Route::delete('/ai-r69a/provider-settings/{provider}', [AiProviderSettingsController::class, 'forget'])
            ->middleware('throttle:5,1')->name('pi.ai.provider-settings.forget');
        Route::get('/ai-r69a/evidence-context', AiEvidenceContextController::class)
            ->name('pi.ai.evidence-context');
        Route::get('/ai-r69a/review-brief', AiReviewBriefController::class)
            ->name('pi.ai.review-brief');
        Route::post('/ai-r69a/local-inference', AiLocalInferenceController::class)
            ->middleware('throttle:3,1')->name('pi.ai.local-inference');
        Route::post('/ai-r69a/cloud-inference', AiCloudInferenceController::class)
            ->middleware('throttle:2,1')->name('pi.ai.cloud-inference');
        Route::post('/auth/logout', [SessionController::class, 'destroy'])->name('auth.logout');
        Route::get('/qa-subscription-r69e/entitlements', EntitlementController::class)
            ->name('pi.entitlements');
        Route::get('/qa-planning-r70k/program-candidate', PlanningProgramCandidateController::class)
            ->name('pi.planning.program-candidate');
        Route::get('/qa-regulatory-r70l/normative-root', NormativeRootController::class)
            ->name('pi.regulatory.normative-root');
        Route::get('/qa-regulatory-r70r/auto-ancestry', AutomatedAncestryController::class)
            ->middleware('throttle:10,1')->name('pi.regulatory.auto-ancestry');
        Route::get('/qa-regulatory-r70o/discover', RegulatoryDiscoveryController::class)
            ->middleware('throttle:10,1')->name('pi.regulatory.discover');
        Route::get('/qa-planning-r71/normative-readiness', NormativePlanningReadinessController::class)
            ->name('pi.planning.normative-readiness');
        Route::get('/qa-planning-r70j/lineage-candidates', PlanningLineageController::class)
            ->name('pi.planning.lineage-candidates');
        Route::get('/qa-regulatory-r70g/laravel-read-model', RegulatoryReadModelController::class)
            ->name('pi.regulatory.read-model');
        Route::get('/qa-regulatory-r70a/trace', RegulatoryLegalTraceController::class)
            ->name('pi.regulatory.legal-trace');
        Route::get('/qa-regulatory-r70b/sources', [RegulatorySnapshotController::class, 'sources'])
            ->name('pi.regulatory.snapshot-catalog');
        Route::get('/qa-regulatory-r70b/pdf', [RegulatorySnapshotController::class, 'pdf'])
            ->middleware('throttle:12,1')->name('pi.regulatory.snapshot-pdf');
        Route::get('/qa-regulatory-r70c/ledger', [RegulatoryLegacyProjectionController::class, 'ledger'])
            ->name('pi.regulatory.legacy-ledger');
        Route::get('/qa-regulatory-r70d/scope', [RegulatoryLegacyProjectionController::class, 'scope'])
            ->name('pi.regulatory.legacy-scope');
        Route::get('/qa-regulatory-r72/change-trace', RegulatoryChangeTraceController::class)
            ->name('pi.regulatory.change-trace');
        Route::get('/qa-regulatory-r74/taxonomy', [RegulatoryGenealogyController::class, 'catalogue'])
            ->middleware('throttle:20,1')->name('pi.regulatory.taxonomy');
        Route::get('/qa-regulatory-r74/genealogy', [RegulatoryGenealogyController::class, 'trace'])
            ->middleware('throttle:20,1')->name('pi.regulatory.genealogy');
        Route::get('/qa-regulatory-r70h/evidence/{sourceId}/{excerptSha256}', RegulatoryEvidenceController::class)
            ->where(['sourceId' => '[A-Z0-9_]+', 'excerptSha256' => '[A-Fa-f0-9]{64}'])
            ->name('pi.regulatory.evidence');
    });
});
