<?php

use App\Services\Pi\Regulatory\OfficialNormativeAncestry;
use App\Services\Pi\Regulatory\OfficialParentEvidenceExpansion;
use App\Services\Pi\Regulatory\OfficialParentResolver;
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

Artisan::command('pi:regulatory-ancestry:resolve-parents', function () {
    if (!config('pi.regulatory_auto_ancestry.enabled', true)) {
        $this->warn('PI_R70S=DISABLED');
        return;
    }

    $cache = Cache::store((string) config('pi.regulatory_auto_ancestry.cache_store', 'file'));
    $graph = $cache->remember(OfficialNormativeAncestry::CACHE_KEY,
        now()->addHours(12),
        fn (): array => app(OfficialNormativeAncestry::class)->trace()
    );
    $updated = app(OfficialParentResolver::class)->resolveBatch($graph);
    $cache->put(OfficialNormativeAncestry::CACHE_KEY, $updated, now()->addHours(12));
    $batch = $updated['parent_resolution_batch'];

    $this->info('PI_R70S=OFFICIAL_PARENT_CATALOG_SEARCH_COMPLETED');
    $this->line('ATTEMPTED='.$batch['attempted_this_run']
        .' MATCHED='.$batch['matched_this_run']
        .' CATALOG_MATCHED_TOTAL='.$batch['catalog_matched_count']
        .' UNRESOLVED='.$updated['unresolved_parent_count'].' LEGAL=ABSTAIN');
})->purpose('Automatically resolve cited parent-law identities via official catalog search');

Artisan::command('pi:regulatory-ancestry:expand-parent-evidence', function () {
    if (!config('pi.regulatory_auto_ancestry.enabled', true)) {
        $this->warn('PI_R70T=DISABLED');
        return;
    }

    $cache = Cache::store((string) config('pi.regulatory_auto_ancestry.cache_store', 'file'));
    $graph = $cache->remember(OfficialNormativeAncestry::CACHE_KEY,
        now()->addHours(12),
        fn (): array => app(OfficialNormativeAncestry::class)->trace()
    );
    $updated = app(OfficialParentEvidenceExpansion::class)->expandBatch($graph);
    $cache->put(OfficialNormativeAncestry::CACHE_KEY, $updated, now()->addHours(12));
    $batch = $updated['parent_evidence_expansion'];

    $this->info('PI_R70T=OFFICIAL_PARENT_EVIDENCE_READ_COMPLETED');
    $this->line('ATTEMPTED='.$batch['attempted_this_run']
        .' READ='.$batch['read_this_run']
        .' NEW_UUD_EDGES='.$batch['new_constitutional_edges']
        .' AMENDMENT_CANDIDATES='.$batch['total_version_candidates'].' LEGAL=ABSTAIN');
    if ($batch['read_this_run'] > 0 && $batch['total_version_candidates'] === 0) {
        $observations = array_values(array_filter(array_map(
            static fn (array $attempt): ?array => $attempt['parser_diagnostics'] ?? null,
            array_values($batch['attempts'] ?? [])
        )));
        $statusSeen = count(array_filter($observations,
            static fn (array $item): bool => ($item['status_seen'] ?? false) === true));
        $headingsSeen = count(array_filter($observations,
            static fn (array $item): bool => ($item['amendment_heading_seen'] ?? false) === true));
        $linkedLabels = array_sum(array_column($observations, 'linked_uu_labels_seen'));
        $rejectedUrls = array_sum(array_column($observations, 'rejected_link_urls'));
        $this->warn('R70T_PARSER_DIAGNOSTICS STATUS_SEEN='.$statusSeen
            .' CHANGE_HEADING_SEEN='.$headingsSeen
            .' LINKED_UU_LABELS='.$linkedLabels.' REJECTED_URLS='.$rejectedUrls);
    }
})->purpose('Autonomously examine official matched parent-law metadata with source hashes');

// R70U: one unattended, bounded progression step for both parent identity and
// official evidence. Saves a checkpoint between stages; never decides legal effect.
Artisan::command('pi:regulatory-ancestry:advance', function () {
    if (!config('pi.regulatory_auto_ancestry.enabled', true)) {
        $this->warn('PI_R70U=DISABLED');
        return;
    }

    $cache = Cache::store((string) config('pi.regulatory_auto_ancestry.cache_store', 'file'));
    $graph = $cache->remember(OfficialNormativeAncestry::CACHE_KEY,
        now()->addHours(12),
        fn (): array => app(OfficialNormativeAncestry::class)->trace()
    );
    $lookups = 0;
    $matches = 0;
    $parentReads = 0;
    $rounds = 0;
    for ($round = 0; $round < 2; $round++) {
        $graph = app(OfficialParentResolver::class)->resolveBatch($graph);
        $resolution = $graph['parent_resolution_batch'];
        $lookups += $resolution['attempted_this_run'];
        $matches += $resolution['matched_this_run'];
        $cache->put(OfficialNormativeAncestry::CACHE_KEY, $graph, now()->addHours(12));

        $graph = app(OfficialParentEvidenceExpansion::class)->expandBatch($graph);
        $evidence = $graph['parent_evidence_expansion'];
        $parentReads += $evidence['read_this_run'];
        $cache->put(OfficialNormativeAncestry::CACHE_KEY, $graph, now()->addHours(12));
        $rounds++;

        if ($resolution['attempted_this_run'] === 0
            && $evidence['attempted_this_run'] === 0) {
            break;
        }
    }

    $this->info('PI_R70U=BOUNDED_ANCESTRY_PROGRESSION_COMPLETED');
    $this->line('ROUNDS='.$rounds.' LOOKUPS='.$lookups.' MATCHED='.$matches
        .' PARENT_PAGES_READ='.$parentReads
        .' CATALOG_TOTAL='.$graph['parent_resolution_batch']['catalog_matched_count']
        .' UNRESOLVED='.$graph['unresolved_parent_count']
        .' AMENDMENT_CANDIDATES='.count($graph['version_candidates'] ?? [])
        .' LEGAL=ABSTAIN');
})->purpose('Progress official parent lookup and evidence automatically without per-law user input');

// Runs automatically when an authorized Laravel scheduler worker is active.
// The authenticated read model also refreshes on a cache miss as a fallback.
Schedule::command('pi:regulatory-ancestry:refresh')->dailyAt('03:30')->timezone('Asia/Jakarta');

// R70U advances both queues unattended, maximum two bounded rounds each run.
// Scheduler activation is separately required; the disabled Windows bridge
// scheduled task is not restarted or modified.
Schedule::command('pi:regulatory-ancestry:advance')
    ->everySixHours()->timezone('Asia/Jakarta')->withoutOverlapping(60);
