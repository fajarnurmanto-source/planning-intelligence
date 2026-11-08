<?php

namespace App\Services\PlanningIntelligence;

use JsonException;
use RuntimeException;

/**
 * Laravel-native, strictly read-only access to official PI QA-derived JSON.
 * No legal conclusion, annex calculation, external requests, or database writes.
 */
final class RegulatorySourceGateway
{
    /** @return array<string, mixed> */
    public function describe(string $path, string $sourceId): array
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException($sourceId.':MISSING_OR_UNREADABLE');
        }
        $size = filesize($path);
        if ($size === false || $size > 32 * 1024 * 1024) {
            throw new RuntimeException($sourceId.':SIZE_NOT_ALLOWED_FOR_QA');
        }
        $raw = file_get_contents($path);
        if (! is_string($raw)) {
            throw new RuntimeException($sourceId.':READ_FAILED');
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException($sourceId.':INVALID_JSON', 0, $e);
        }
        if (! is_array($data)) {
            throw new RuntimeException($sourceId.':NOT_STRUCTURED_JSON');
        }

        return [
            'source' => $sourceId,
            'sha256' => hash('sha256', $raw),
            'size_bytes' => strlen($raw),
            'top_level_items' => count($data),
            'top_level_keys' => array_slice(array_keys($data), 0, 24),
            'content_status' => 'METADATA_ONLY_NO_LEGAL_INFERENCE',
        ];
    }

    /** @return array<string, mixed> */
    public function baseline(string $ledgerPath, string $scopePath): array
    {
        return [
            'project' => 'Planning Intelligence',
            'module' => 'Regulatory Intelligence',
            'phase' => 'R70E_LARAVEL_READ_ONLY_SOURCE_GATEWAY',
            'inputs' => [
                $this->describe($ledgerPath, 'R70C_LEDGER'),
                $this->describe($scopePath, 'R70D_SCOPE'),
            ],
            'legal_harmonization' => 'NOT_ASSESSED',
            'annex_numeric_comparison' => 'NOT_PERFORMED',
            'version_reconciliation' => 'NOT_PERFORMED',
            'production_rights' => 'DENIED',
        ];
    }
}
