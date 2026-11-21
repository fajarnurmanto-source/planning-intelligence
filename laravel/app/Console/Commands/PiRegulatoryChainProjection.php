<?php

namespace App\Console\Commands;

use App\Services\PlanningIntelligence\RegulatoryChainProjector;
use Illuminate\Console\Command;
use Throwable;

/** Read-only application entry point; no HTTP route or database table. */
final class PiRegulatoryChainProjection extends Command
{
    protected $signature = 'pi:regulatory-chain-projection
        {--ledger= : Absolute R70C ledger JSON path}
        {--scope= : Absolute R70D scope JSON path}';

    protected $description = 'Build a source-anchored regulatory chain in Laravel QA';

    public function handle(RegulatoryChainProjector $projector): int
    {
        $ledger = $this->option('ledger');
        $scope = $this->option('scope');
        if (! is_string($ledger) || ! is_string($scope) || $ledger === '' || $scope === '') {
            $this->error('PI_R70F=SOURCE_PATHS_REQUIRED');

            return self::FAILURE;
        }
        try {
            $model = $projector->project($ledger, $scope);
            $this->line('PI_R70F_JSON='.json_encode(
                $model, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ));
            $this->info('PI_R70F=PASS_LARAVEL_REGULATORY_READ_MODEL');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('PI_R70F=BLOCKED:'.$error->getMessage());

            return self::FAILURE;
        }
    }
}
