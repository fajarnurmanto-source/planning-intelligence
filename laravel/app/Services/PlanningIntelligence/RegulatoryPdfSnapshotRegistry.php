<?php

namespace App\Services\PlanningIntelligence;

use JsonException;
use RuntimeException;

/**
 * Local QA PDF snapshots are opt-in. An operator-configured manifest maps a
 * source ID from the validated R70F ledger to a path RELATIVE to a fixed root.
 * No input may supply an arbitrary pathname or remote URL.
 */
final class RegulatoryPdfSnapshotRegistry
{
    private const MAX_PDF_BYTES = 48 * 1024 * 1024;

    /** @param array<string, mixed> $model
     *  @return array<string, array{path:string, size:int, sha256:string}>
     */
    public function verifiedFiles(array $model): array
    {
        $rootSetting = (string) config('pi.regulatory.pdf_root');
        $manifestSetting = (string) config('pi.regulatory.pdf_manifest_path');
        if ($rootSetting === '' && $manifestSetting === '') {
            return []; // Local PDF access is opt-in; source metadata still works.
        }
        if ($rootSetting === '' || $manifestSetting === '') {
            throw new RuntimeException('PDF_REGISTRY_CONFIGURATION_INCOMPLETE');
        }
        $root = realpath($rootSetting);
        if ($root === false || ! is_dir($root) || ! is_readable($root)) {
            throw new RuntimeException('PDF_ROOT_NOT_READABLE');
        }
        if (! is_file($manifestSetting) || ! is_readable($manifestSetting) ||
            filesize($manifestSetting) > 1024 * 1024) {
            throw new RuntimeException('PDF_MANIFEST_NOT_READABLE');
        }
        $raw = file_get_contents($manifestSetting);
        if (! is_string($raw)) {
            throw new RuntimeException('PDF_MANIFEST_READ_FAILED');
        }
        try {
            $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('PDF_MANIFEST_JSON_INVALID', 0, $e);
        }
        if (! is_array($manifest) ||
            ($manifest['schema'] ?? null) !== 'PI_R78_LOCAL_PDF_INDEX_V1' ||
            ($manifest['tenant_id'] ?? null) !== ($model['tenant_id'] ?? null) ||
            ($manifest['tenant_id'] ?? null) !== config('pi.tenant_id') ||
            ! is_array($manifest['sources'] ?? null) ||
            array_is_list($manifest['sources'])) {
            throw new RuntimeException('PDF_MANIFEST_CONTRACT_INVALID');
        }

        $expected = [];
        foreach (($model['documents'] ?? []) as $source) {
            $expected[$source['id']] = strtoupper($source['pdf_sha256']);
        }

        $files = [];
        foreach ($manifest['sources'] as $id => $row) {
            if (! is_string($id) || ! isset($expected[$id]) || ! is_array($row)) {
                throw new RuntimeException('PDF_MANIFEST_SOURCE_UNKNOWN');
            }
            $relative = $row['relative_path'] ?? null;
            $pinned = $row['sha256'] ?? null;
            if (! is_string($relative) || $relative === '' || strlen($relative) > 512 ||
                str_contains($relative, '\\') || str_starts_with($relative, '/') ||
                preg_match('/(^|\/)\.\.?($|\/)/', $relative) ||
                ! preg_match('/\.pdf$/i', $relative) ||
                ! is_string($pinned) || strtoupper($pinned) !== $expected[$id]) {
                throw new RuntimeException('PDF_MANIFEST_ENTRY_REJECTED');
            }

            $candidate = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));
            if ($candidate === false || ! is_file($candidate) || ! is_readable($candidate)) {
                // Snapshots are allowed to be absent; do not invent availability.
                continue;
            }
            $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/').'/';
            $normalizedFile = str_replace('\\', '/', $candidate);
            if (stripos($normalizedFile, $normalizedRoot) !== 0) {
                throw new RuntimeException('PDF_PATH_ESCAPES_ROOT');
            }
            $size = filesize($candidate);
            if (! is_int($size) || $size < 5 || $size > self::MAX_PDF_BYTES) {
                throw new RuntimeException('PDF_SIZE_REJECTED');
            }
            $fingerprint = strtoupper((string) hash_file('sha256', $candidate));
            if ($fingerprint !== $expected[$id]) {
                throw new RuntimeException('PDF_HASH_MISMATCH');
            }
            $signature = file_get_contents($candidate, false, null, 0, 5);
            if ($signature !== '%PDF-') {
                throw new RuntimeException('PDF_SIGNATURE_INVALID');
            }
            $files[$id] = ['path' => $candidate, 'size' => $size, 'sha256' => $fingerprint];
        }
        return $files;
    }
}
