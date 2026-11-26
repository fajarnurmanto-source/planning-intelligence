<?php

namespace App\Services\Pi\Regulatory;

use RuntimeException;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * No network, no AI, no OCR. Reads the already pinned PDFs with a local
 * Poppler pdftotext executable (or an existing WSL pdftotext). If unavailable
 * or scanned, the evidence gate abstains and never invents an excerpt.
 */
final class R82NationalEvidencePipeline
{
    public function __construct(
        private readonly R82NationalSourceVerifier $verifier,
        private readonly R82ProvisionTextScanner $scanner,
        private readonly RegulatoryDelegationCoverage $r80,
    ) {
    }

    public function snapshotDirectory(): string
    {
        return dirname(base_path(), 3).DIRECTORY_SEPARATOR
            .'08_RUNTIME'.DIRECTORY_SEPARATOR.'R81_NATIONAL_PDF_SNAPSHOTS';
    }

    public function outputPath(): string
    {
        return dirname(base_path(), 3).DIRECTORY_SEPARATOR.'08_RUNTIME'
            .DIRECTORY_SEPARATOR.'R82_NATIONAL_PASSAGE_CANDIDATES'
            .DIRECTORY_SEPARATOR.'candidate-manifest.json';
    }

    /** @return array<string,mixed> */
    public function build(?string $root = null, ?array $texts = null): array
    {
        $verified = $this->verifier->verify($root ?? $this->snapshotDirectory());
        $coverage = $this->r80->project();
        if (($coverage['candidate_article_count'] ?? null) !== 7
            || ($coverage['source_count'] ?? null) !== 1
            || ($coverage['tenant_id'] ?? null) !== 'ID-DIY-YOG'
            || ($coverage['legal_harmonization'] ?? null) !== 'NOT_ASSESSED'
            || ($coverage['production_admission'] ?? null) !== 'DENIED') {
            throw new RuntimeException('R82_R80_INPUT_GUARD_REJECTED');
        }
        $candidates = [];
        foreach ($coverage['sources'][0]['candidates'] as $candidate) {
            $candidates[] = ['article' => $candidate['article'], 'paragraphs' => $candidate['paragraphs']];
        }

        $statuses = [];
        $actualTexts = [];
        foreach (['PP-81-2012', 'UU-18-2008'] as $id) {
            if ($texts !== null) {
                $body = $texts[$id] ?? null;
            } else {
                $body = $this->extract($verified['documents'][$id]['path']);
            }
            $usable = is_string($body) && $body !== ''
                && strlen($body) <= 3_000_000 && mb_check_encoding($body, 'UTF-8')
                && preg_match('/\p{L}/u', $body) === 1;
            $statuses[$id] = $usable ? 'LOCAL_TEXT_LAYER_EXTRACTED' : 'TEXT_LAYER_UNAVAILABLE_ABSTAIN';
            $actualTexts[$id] = $usable ? $body : '';
        }

        $scan = $this->scanner->scan(
            $actualTexts['PP-81-2012'],
            $actualTexts['UU-18-2008'],
            $candidates
        );

        $sources = [];
        foreach ($verified['documents'] as $id => $document) {
            $sources[] = [
                'instrument_id' => $id,
                'pdf_sha256' => $document['sha256'],
                'pdf_bytes' => $document['bytes'],
                'acquisition_url' => $document['acquisition_url'],
                'text_layer_status' => $statuses[$id],
                'source_identity' => 'REVIEW_REQUIRED',
            ];
        }
        return [
            'schema' => 'PI_R82_NATIONAL_PASSAGE_CANDIDATES_QA_V1',
            'tenant_id' => 'ID-DIY-YOG',
            'source_snapshot_integrity' => 'R81_TWO_INDEPENDENT_SHA256_PINS_MATCH',
            'r81_manifest_sha256' => $verified['manifest_sha256'],
            'sources' => $sources,
            'r80_candidate_article_count' => count($scan['records']),
            'text_candidate_article_count' => $scan['extracted'],
            'unresolved_article_count' => $scan['unresolved'],
            'candidates' => $scan['records'],
            'passage_verification' => 'UNREVIEWED_TEXT_LAYER_CANDIDATES_ONLY',
            'review_status' => 'REVIEW_REQUIRED',
            'relation_admission' => 'NOT_PERFORMED',
            'legal_harmonization' => 'NOT_ASSESSED',
            'temporal_applicability' => 'NOT_ASSESSED',
            'numeric_comparison' => 'BLOCKED',
            'production_admission' => 'DENIED',
        ];
    }

    public function extract(string $file): ?string
    {
        $commands = [['pdftotext', '-layout', '-enc', 'UTF-8', $file, '-']];
        if (PHP_OS_FAMILY === 'Windows'
            && preg_match('~^([a-zA-Z]):[\\\\/](.+)$~', $file, $parts)) {
            $wslPath = '/mnt/'.strtolower($parts[1]).'/'
                .str_replace('\\', '/', $parts[2]);
            $commands[] = ['wsl.exe', '-d', 'Ubuntu-24.04', '-e', 'pdftotext',
                '-layout', '-enc', 'UTF-8', $wslPath, '-'];
        }
        foreach ($commands as $args) {
            try {
                $process = new Process($args);
                $process->setTimeout(35);
                $process->run();
                if (! $process->isSuccessful()) {
                    continue;
                }
                $result = $process->getOutput();
                if (strlen($result) > 3_000_000 || ! mb_check_encoding($result, 'UTF-8')) {
                    continue;
                }
                return $result;
            } catch (ExceptionInterface|\RuntimeException $error) {
                // A missing local extractor is NOT an invitation to fabricate text.
                continue;
            }
        }
        return null;
    }
}
