<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Strictly read-only bridge to PI R70C / R70D source QA metadata.
 * This command does not interpret legal validity, reconcile budget values,
 * connect to any Python service, or modify evidence snapshots.
 */
class PiRegulatoryQaInventory extends Command
{
    protected $signature = 'pi:regulatory-qa-inventory
        {--ledger= : Absolute path to R70C amendment_ledger.json}
        {--scope= : Absolute path to R70D operative_scope.json}';

    protected $description = 'Inspect R70C/R70D QA source metadata without modifying originals';

    public function handle(): int
    {
        $files = [
            'R70C_LEDGER' => $this->option('ledger'),
            'R70D_SCOPE' => $this->option('scope'),
        ];

        foreach ($files as $label => $path) {
            if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path)) {
                $this->error("PI_BRIDGE={$label}:MISSING_OR_UNREADABLE");

                return self::FAILURE;
            }
            $raw = file_get_contents($path);
            if (! is_string($raw)) {
                $this->error("PI_BRIDGE={$label}:READ_FAILED");

                return self::FAILURE;
            }
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                $this->error("PI_BRIDGE={$label}:INVALID_JSON");

                return self::FAILURE;
            }
            if (! is_array($decoded)) {
                $this->error("PI_BRIDGE={$label}:INVALID_TOP_LEVEL_DATA");

                return self::FAILURE;
            }
            $this->line("PI_BRIDGE={$label}:READ_ONLY_VALID_JSON:SHA256=".hash('sha256', $raw)
                .':TOP_LEVEL_ITEMS='.count($decoded));
        }

        $this->info('PI_BRIDGE=PASS_SOURCE_QA_INVENTORY_ONLY');
        $this->warn('LEGAL_HARMONIZATION=NOT_ASSESSED; NUMERIC_COMPARISON=BLOCKED; PRODUCTION=DENIED');

        return self::SUCCESS;
    }
}
