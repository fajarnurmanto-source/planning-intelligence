<?php

use App\Services\Pi\Regulatory\R82NationalEvidencePipeline;
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


// R82: explicit local-only command. Reuses R81 pinned bytes and R80 candidates.
// It never writes to R81, edits the law graph, calls AI, or approves legal effects.
Artisan::command('pi:r82:build', function (): int {
    try {
        $pipeline = app(R82NationalEvidencePipeline::class);
        $report = $pipeline->build();
        $this->line('PI_R82_SOURCE_INTEGRITY=PASS_2_OF_2');
        $this->line('PI_R82_TEXT_PP='.$report['sources'][0]['text_layer_status']);
        $this->line('PI_R82_TEXT_UU='.$report['sources'][1]['text_layer_status']);

        if (count(array_filter($report['sources'],
            static fn (array $source): bool =>
                $source['text_layer_status'] === 'LOCAL_TEXT_LAYER_EXTRACTED')) !== 2) {
            $this->warn('PI_R82_TEXT_EXTRACTION=ABSTAIN_EXTRACTOR_OR_TEXT_LAYER_UNAVAILABLE');
            $this->warn('PI_R82_REPORT_NOT_WRITTEN=YES');
            return 2;
        }

        $json = json_encode($report,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        $destination = $pipeline->outputPath();
        $dir = dirname($destination);
        if (! is_dir($dir) && ! mkdir($dir, 0750, true) && ! is_dir($dir)) {
            throw new RuntimeException('R82_OUTPUT_DIRECTORY_UNAVAILABLE');
        }

        if (is_file($destination)) {
            if (file_get_contents($destination) !== $json) {
                throw new RuntimeException('R82_EXISTING_REPORT_DIFF_REVIEW_REQUIRED');
            }
            $this->line('PI_R82_REPORT=ALREADY_PREPARED');
        } else {
            $tmp = $destination.'.tmp-'.bin2hex(random_bytes(8));
            if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json)) {
                @unlink($tmp);
                throw new RuntimeException('R82_REPORT_WRITE_FAILED');
            }
            if (file_exists($destination) || ! rename($tmp, $destination)) {
                @unlink($tmp);
                throw new RuntimeException('R82_REPORT_PUBLISH_FAILED');
            }
            $this->info('PI_R82_REPORT=PREPARED');
        }

        $this->line('PI_R82_CANDIDATES='.$report['r80_candidate_article_count']);
        $this->line('PI_R82_TEXT_CANDIDATES='.$report['text_candidate_article_count']);
        $this->line('PI_R82_UNRESOLVED='.$report['unresolved_article_count']);
        $this->line('PI_R82_HUMAN_REVIEW=REQUIRED');
        $this->line('PI_R82_RELATION_ADMISSION=NOT_PERFORMED');
        $this->line('PI_R82_PRODUCTION_ADMISSION=DENIED');
        $this->line('PI_R82_LOCAL_REPORT=PASS');
        return 0;
    } catch (Throwable $error) {
        report($error);
        $this->error('PI_R82_BUILD_BLOCKED='.$error->getMessage());
        return 1;
    }
})->purpose('Extract review-only national provision candidates from immutable R81 snapshots');


// RAI is opt-in; existing launcher/worker is not changed.
Artisan::command('pi:rai:enqueue {document}', function (): int {
    if(!config('pi_rai.enabled',false)){$this->warn('PI_RAI_DISABLED');return 2;}
    if(config('queue.default')!=='database'){$this->warn('PI_RAI_PERSISTENT_QUEUE_REQUIRED');return 2;}
    $q=json_decode(file_get_contents(resource_path('pi/rai-source-registry.json')),true,32,JSON_THROW_ON_ERROR);
    $id=$this->argument('document');
    if(!in_array($id,array_column($q['documents'],'id'),true)){$this->warn('PI_RAI_UNKNOWN_DOCUMENT');return 2;}
    \App\Jobs\AcquirePinnedRegulation::dispatch($id);
    $this->info('PI_RAI_ENQUEUED='.$id);
    return 0;
})->purpose('Queue a known public regulation candidate without blocking analysis');

Artisan::command('pi:rai:status {document}', function (): int {
    $q=json_decode(file_get_contents(resource_path('pi/rai-source-registry.json')),true,32,JSON_THROW_ON_ERROR);
    $id=$this->argument('document');
    if(!in_array($id,array_column($q['documents'],'id'),true))return 2;
    $r=Cache::store('file')->get('pi-rai-result:'.$id,['state'=>'NO_WORKER_RESULT']);
    $this->line(json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
    return 0;
})->purpose('Read last public candidate acquisition result');
