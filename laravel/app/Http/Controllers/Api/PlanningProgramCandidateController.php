<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * One documentary program/performance node from public RKPD text.
 * Not a confirmed code-based Regulatory→Planning crosswalk.
 */
final class PlanningProgramCandidateController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $path = (string) config('pi.planning_node.registry_path');
            $raw = is_file($path) ? file_get_contents($path) : false;
            if (!is_string($raw) || $raw === '' || strlen($raw) > 128 * 1024) {
                throw new RuntimeException('Planning evidence registry unreadable.');
            }

            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_is_list($data)) {
                throw new RuntimeException('Planning evidence registry malformed.');
            }
            $this->assertSource($data);
            $this->assertEvidence($data['node']);
            $this->assertAbstention($data['crosswalk']);

            return response()->json([
                'schema' => 'PI_R70K_TYPED_PLANNING_CROSSWALK_V1',
                'tenant_id' => $data['jurisdiction_id'],
                'review_year' => $data['review_year'],
                'source_registry_sha256' => strtoupper(hash('sha256', $raw)),
                'source_integrity' => 'METADATA_REGISTRY_ONLY_UNPINNED_PDF',
                'data_rights' => $data['data_rights'],
                'document' => $data['document'],
                'planning_node' => $data['node'],
                'crosswalk' => $data['crosswalk'],
                'interpretation' => [
                    'review_status' => 'REVIEW_REQUIRED',
                    'legal_harmonization' => 'NOT_ASSESSED',
                    'substantive_alignment' => 'NOT_ASSESSED',
                    'numeric_comparison' => 'BLOCKED',
                    'production_admission' => 'DENIED',
                ],
            ])->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'REJECTED',
                'code' => 'PLANNING_NODE_SOURCE_UNAVAILABLE',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }

    private function assertSource(array $data): void
    {
        if (($data['schema'] ?? null) !== 'PI_R70K_TYPED_PLANNING_EVIDENCE_V1'
            || ($data['jurisdiction_id'] ?? null) !== 'ID-DIY-YOG'
            || ($data['jurisdiction_id'] ?? null) !== config('pi.tenant_id')
            || ($data['review_year'] ?? null) !== 2026
            || ($data['observed_on'] ?? null) !== '2026-11-08'
            || ($data['data_rights'] ?? null) !== 'PUBLIC_OFFICIAL_DOCUMENT_METADATA_WITH_SOURCE_LOCATOR_ONLY'
            || ($data['numeric_comparison'] ?? null) !== 'BLOCKED'
            || ($data['legal_harmonization'] ?? null) !== 'NOT_ASSESSED'
            || ($data['production_admission'] ?? null) !== 'DENIED'
            || !is_array($data['document'] ?? null)
            || !is_array($data['node'] ?? null)
            || !is_array($data['crosswalk'] ?? null)) {
            throw new RuntimeException('Planning node contract invalid.');
        }
        $document = $data['document'];
        $expected = [
            'id' => 'ID-DIY-YOG-RKPD-2026-PERWALI-34-2025',
            'kind' => 'RKPD',
            'title' => 'Peraturan Wali Kota Yogyakarta Nomor 34 Tahun 2025 tentang Rencana Kerja Pemerintah Daerah Tahun 2026',
            'issued_on' => '2025-07-07',
            'plan_period_from' => 2026,
            'plan_period_to' => 2026,
            'source_url' => 'https://jdih.jogjakota.go.id/asset/naskah/2025pw3405034.pdf',
            'metadata_url' => 'https://jdih.jogjakota.go.id/home/produk_hukum/detail/3758',
            'pdf_sha256' => null,
            'pdf_snapshot_status' => 'NOT_CAPTURED_NO_HASH',
        ];
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $document) || $document[$key] !== $value) {
                throw new RuntimeException('Document source mismatch: '.$key);
            }
        }
    }

    private function assertEvidence(array $node): void
    {
        $expected = [
            'id' => 'PI-R70K-RKPD-2026-JALAN-INDEX-01',
            'taxonomy_type' => 'PROGRAM_NARRATIVE_WITH_PERFORMANCE_INDICATOR',
            'responsible_unit' => 'Dinas Pekerjaan Umum, Perumahan, dan Kawasan Permukiman Kota Yogyakarta',
            'program_name_as_observed' => 'program penyelenggaraan jalan',
            'normalized_program_name' => 'Program Penyelenggaraan Jalan',
            'official_program_code' => null,
            'official_program_code_status' => 'NOT_PRESENT_IN_REVIEWED_EXCERPT',
            'indicator_name' => 'Indeks prasarana umum wilayah',
            'indicator_unit' => 'angka indeks',
            'historical_observation_year' => 2024,
            'historical_value_as_printed' => '92,16',
            'planning_target_year' => 2026,
            'planning_target_value' => null,
            'relation_type' => 'NARRATIVE_COMPONENT_OF_COMPOSITE_INDICATOR',
            'relation_evidence' => 'Indeks prasarana umum wilayah dihitung dari pembobotan capaian kinerja pada program penyelenggaraan jalan, bersama beberapa program pekerjaan umum lain.',
            'source_locator' => 'Lampiran BAB II, subbab C Pekerjaan Umum dan Penataan Ruang, Tabel 2.66, halaman cetak II-77',
            'source_printed_page' => 'II-77',
            'source_pdf_page' => null,
            'source_status' => 'PUBLIC_WEB_TEXT_LOCATED_PDF_NOT_HASH_PINNED',
            'reviewer_status' => 'REVIEW_REQUIRED',
        ];
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $node) || $node[$key] !== $value) {
                throw new RuntimeException('Planning node evidence mismatch: '.$key);
            }
        }
    }

    private function assertAbstention(array $crosswalk): void
    {
        $expected = [
            'target_regulatory_model' => 'PI_R70F_LARAVEL_REGULATORY_READ_MODEL_V1',
            'required_same_jurisdiction' => true,
            'required_official_program_code' => true,
            'required_matching_fiscal_year' => true,
            'required_source_pdf_snapshot' => true,
            'admitted_regulatory_relation' => null,
            'status' => 'ABSTAIN',
            'reason' => 'NO_VERIFIED_2026_PROGRAM_CODE_OR_ROW_LEVEL_REGULATORY_MATCH',
            'program_to_amendment_mapping' => 'NOT_ESTABLISHED',
            'substantive_alignment' => 'NOT_ASSESSED',
        ];
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $crosswalk) || $crosswalk[$key] !== $value) {
                throw new RuntimeException('Planning crosswalk guard mismatch: '.$key);
            }
        }
    }
}
