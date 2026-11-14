<?php

namespace App\Services\Pi\Regulatory;

use JsonException;
use RuntimeException;

/**
 * R82: check the two R81 snapshots against both the immutable manifest and
 * the independently accepted operator SHA256 pins, before reading any text.
 * Never downloads, mutates R81, or promotes a legal relation.
 */
final class R82NationalSourceVerifier
{
    private const PINS = [
        'PP-81-2012' => [
            'file' => 'pdf/PP-81-2012.pdf',
            'sha256' => '542CE23457FE19529887D3B0BD4B14FAAE7F6F1E269CBFC5B840E117B2CDEE53',
            'urls' => ['https://www.peraturan.go.id/files/pp81-2012bt.pdf'],
        ],
        'UU-18-2008' => [
            'file' => 'pdf/UU-18-2008.pdf',
            'sha256' => 'DC7735DB65D6DEDC650FF78C36B6C6F076CF931861B005EDAB03F30E513026F4',
            'urls' => [
                'https://peraturan.bpk.go.id/Download/28462/UU%20Nomor%2018%20Tahun%202008.pdf',
                'https://jdihn.go.id/file/download?id_anggota=2098&id_dokumen=1369',
                'https://www.peraturan.go.id/files/UU%2B18%2BTahun%2B2008.pdf',
                'https://peraturan.go.id/files/UU%2B18%2BTahun%2B2008.pdf',
                'https://dpr.go.id/dokjdih/document/uu/UU_2008_18.pdf',
            ],
        ],
    ];

    /**
     * @return array{manifest_sha256:string,documents:array<string,array{path:string,sha256:string,bytes:int,acquisition_url:string}>}
     */
    public function verify(string $directory): array
    {
        $root = realpath($directory);
        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('R82_SNAPSHOT_DIRECTORY_MISSING');
        }
        $manifestPath = $root.DIRECTORY_SEPARATOR.'observed-manifest.json';
        if (! is_file($manifestPath) || ! is_readable($manifestPath)
            || filesize($manifestPath) > 1024 * 1024) {
            throw new RuntimeException('R82_R81_MANIFEST_UNAVAILABLE');
        }
        $raw = file_get_contents($manifestPath);
        if (! is_string($raw)) {
            throw new RuntimeException('R82_R81_MANIFEST_UNREADABLE');
        }
        try {
            $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('R82_R81_MANIFEST_INVALID_JSON', 0, $error);
        }
        if (! is_array($manifest)
            || ($manifest['schema'] ?? null) !== 'PI_R81_NATIONAL_PDF_OBSERVED_SNAPSHOTS_V1'
            || ($manifest['tenant_id'] ?? null) !== 'ID-DIY-YOG'
            || ($manifest['source_count'] ?? null) !== 2
            || ($manifest['source_identity_certification'] ?? null) !== 'NOT_PERFORMED'
            || ($manifest['source_passage_verification'] ?? null) !== 'NOT_PERFORMED'
            || ($manifest['r80_relation_promotion'] ?? null) !== 'NOT_PERFORMED'
            || ($manifest['legal_harmonization'] ?? null) !== 'NOT_ASSESSED'
            || ($manifest['production_admission'] ?? null) !== 'DENIED'
            || ! is_array($manifest['sources'] ?? null)
            || ! array_is_list($manifest['sources'])
            || count($manifest['sources']) !== 2) {
            throw new RuntimeException('R82_R81_MANIFEST_GUARDS_REJECTED');
        }
        $verified = [];
        foreach ($manifest['sources'] as $record) {
            if (! is_array($record) || ! is_string($record['instrument_id'] ?? null)
                || ! isset(self::PINS[$record['instrument_id']])
                || isset($verified[$record['instrument_id']])) {
                throw new RuntimeException('R82_SOURCE_ID_INVALID_OR_DUPLICATE');
            }
            $id = $record['instrument_id'];
            $pin = self::PINS[$id];
            if (($record['relative_path'] ?? null) !== $pin['file']
                || ($record['sha256_observed'] ?? null) !== $pin['sha256']
                || ($record['acquisition_mode'] ?? null) !== 'OFFICIAL_HTTPS_NO_REDIRECT'
                || ! is_string($record['acquisition_url'] ?? null)
                || ! in_array($record['acquisition_url'], $pin['urls'], true)
                || ($record['source_identity'] ?? null) !== 'REVIEW_REQUIRED'
                || ($record['primary_passage_evidence'] ?? null) !== 'NOT_VERIFIED'
                || ($record['legal_harmonization'] ?? null) !== 'NOT_ASSESSED') {
                throw new RuntimeException('R82_SOURCE_PROVENANCE_REJECTED');
            }
            $candidate = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $pin['file']));
            $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/').'/';
            if ($candidate === false || ! str_starts_with(str_replace('\\', '/', $candidate), $normalizedRoot)
                || ! is_file($candidate) || ! is_readable($candidate)) {
                throw new RuntimeException('R82_SOURCE_OUTSIDE_CANONICAL_ROOT');
            }
            $bytes = filesize($candidate);
            if (! is_int($bytes) || $bytes < 128 || $bytes > 32 * 1024 * 1024
                || ($record['bytes'] ?? null) !== $bytes) {
                throw new RuntimeException('R82_SOURCE_SIZE_REJECTED');
            }
            $hash = strtoupper((string) hash_file('sha256', $candidate));
            if ($hash !== $pin['sha256']) {
                throw new RuntimeException('R82_SOURCE_HASH_DRIFT');
            }
            $stream = fopen($candidate, 'rb');
            if ($stream === false) {
                throw new RuntimeException('R82_SOURCE_CANNOT_OPEN');
            }
            try {
                $header = fread($stream, 5);
                fseek($stream, -min(2048, $bytes), SEEK_END);
                $footer = stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
            if ($header !== '%PDF-' || ! is_string($footer) || ! str_contains($footer, '%%EOF')) {
                throw new RuntimeException('R82_SOURCE_NOT_PDF');
            }
            $verified[$id] = [
                'path' => $candidate,
                'sha256' => $hash,
                'bytes' => $bytes,
                'acquisition_url' => $record['acquisition_url'],
            ];
        }
        if (count($verified) !== count(self::PINS)) {
            throw new RuntimeException('R82_SOURCE_COUNT_INCOMPLETE');
        }
        ksort($verified);
        return [
            'manifest_sha256' => strtoupper(hash('sha256', $raw)),
            'documents' => $verified,
        ];
    }
}
