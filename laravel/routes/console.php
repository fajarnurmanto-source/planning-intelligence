<?php

use App\Services\Pi\Regulatory\OfficialNormativeAncestry;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pi:regulatory-ancestry:refresh', function () {
    if (!config('pi.regulatory_auto_ancestry.enabled', true)) {
        $this->warn('PI_R70R=DISABLED');
        return;
    }

    $result = app(OfficialNormativeAncestry::class)->trace();
    Cache::store((string) config('pi.regulatory_auto_ancestry.cache_store', 'file'))
        ->put(OfficialNormativeAncestry::CACHE_KEY, $result, now()->addHours(12));

    $this->info('PI_R70R=SOURCE_CITATION_GRAPH_REFRESHED');
    $this->line('SEEDS='.$result['resolved_seed_count'].' EDGES='.count($result['edges'])
        .' UNRESOLVED='.$result['unresolved_parent_count'].' LEGAL=ABSTAIN');
})->purpose('Refresh official regulatory ancestry citations without user-driven searches');

// Runs automatically when an authorized Laravel scheduler worker is active.
// The authenticated read model also refreshes on a cache miss as a fallback.
Schedule::command('pi:regulatory-ancestry:refresh')->dailyAt('03:30')->timezone('Asia/Jakarta');
