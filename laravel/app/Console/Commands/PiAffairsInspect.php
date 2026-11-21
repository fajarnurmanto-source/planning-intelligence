<?php

namespace App\Console\Commands;

use App\Services\Pi\Taxonomy\AffairFirstNomenclature;
use Illuminate\Console\Command;

final class PiAffairsInspect extends Command
{
    protected $signature = 'pi:affairs-inspect {code?} {--year=2026}';

    protected $description = 'Inspect bounded affairs/nomenclature evidence without legal or production admission.';

    public function handle(): int
    {
        try {
            $registry = new AffairFirstNomenclature(resource_path('pi/affairs-nomenclature-seed-r87.json'));
            $year = filter_var($this->option('year'), FILTER_VALIDATE_INT);
            if ($year === false) throw new \RuntimeException('INVALID_YEAR');
            $code = $this->argument('code');
            $data = $code === null ? $registry->catalogue() : $registry->classify((string) $code, $year);
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
