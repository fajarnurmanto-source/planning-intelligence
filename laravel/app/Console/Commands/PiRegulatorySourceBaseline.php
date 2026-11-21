<?php

namespace App\Console\Commands;

use App\Services\PlanningIntelligence\RegulatorySourceGateway;
use Illuminate\Console\Command;
use Throwable;

/** Laravel automatically registers commands in app/Console/Commands. */
final class PiRegulatorySourceBaseline extends Command
{
    protected $signature = 'pi:regulatory-source-baseline
        {--ledger= : Absolute path to R70C amendment_ledger.json}
        {--scope= : Absolute path to R70D operative_scope.json}';

    protected $description = 'Read source-backed R70C/R70D QA metadata via a Laravel domain service';

    public function handle(RegulatorySourceGateway $gateway): int
    {
        $ledger = $this->option('ledger');
        $scope = $this->option('scope');
        if (! is_string($ledger) || ! is_string($scope) || $ledger === '' || $scope === '') {
            $this->error('PI_PHASE1=SOURCE_PATHS_REQUIRED');

            return self::FAILURE;
        }
        try {
            $output = $gateway->baseline($ledger, $scope);
            $json = json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $this->line($json);
            $this->info('PI_PHASE1=PASS_READ_ONLY_GATEWAY');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('PI_PHASE1=REVIEW_REQUIRED:'.$error->getMessage());

            return self::FAILURE;
        }
    }
}
