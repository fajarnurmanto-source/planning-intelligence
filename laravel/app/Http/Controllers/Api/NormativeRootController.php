<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pi\Regulatory\NormativeVersionTimeline;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * R70L is a narrowly source-bound normative ancestry read model, not a legal opinion.
 * The public-PDF URLs are not SHA-pinned. An unverified amendment chain blocks alignment.
 */
final class NormativeRootController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            $path = (string) config('pi.normative_root.registry_path');
            $raw = is_file($path) ? file_get_contents($path) : false;
            if (!is_string($raw) || $raw === '' || strlen($raw) > 128 * 1024) {
                throw new RuntimeException('Normative source registry missing.');
            }

            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_is_list($data)) {
                throw new RuntimeException('Normative source registry invalid.');
            }
            $this->assertRegistry($data);
            $timeline = app(NormativeVersionTimeline::class)->resolve(
                $data['instruments'], $data['version_events'], $data['observed_on']
            );
            if ($timeline['status'] !== $data['gates']['regulatory_version']) {
                throw new RuntimeException('Computed version status contradicts admission gate.');
            }

            return response()->json([
                'schema' => 'PI_R70L_NORMATIVE_ROOT_READ_MODEL_V1',
                'tenant_id' => $data['jurisdiction_id'],
                'review_year' => $data['review_year'],
                'as_of_date' => $data['observed_on'],
                'source_registry_sha256' => strtoupper(hash('sha256', $raw)),
                'source_integrity' => $data['source_integrity'],
                'access_policy' => $data['access_policy'],
                'instruments' => $data['instruments'],
                'provisions' => $data['provisions'],
                'delegation' => $data['delegation'],
                'case' => $data['case'],
                'gates' => $data['gates'],
                'version_timeline' => $timeline,
                'census_snapshot' => $data['census_snapshot'],
            ])->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'REJECTED',
                'code' => 'NORMATIVE_ROOT_SOURCE_UNAVAILABLE',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }

    private function assertRegistry(array $data): void
    {
        if (($data['schema'] ?? null) !== 'PI_R70L_NORMATIVE_ROOT_EVIDENCE_V1'
            || ($data['jurisdiction_id'] ?? null) !== 'ID-DIY-YOG'
            || ($data['jurisdiction_id'] ?? null) !== config('pi.tenant_id')
            || ($data['review_year'] ?? null) !== 2026
            || ($data['observed_on'] ?? null) !== '2026-11-08'
            || ($data['access_policy'] ?? null) !== 'PUBLIC_OFFICIAL_NORMATIVE_METADATA_NO_PDF_REPUBLICATION'
            || ($data['source_integrity'] ?? null) !== 'OFFICIAL_LIVE_PDF_LINKS_NOT_HASH_PINNED'
            || !is_array($data['instruments'] ?? null) || count($data['instruments']) !== 2
            || !is_array($data['provisions'] ?? null) || count($data['provisions']) !== 6
            || !is_array($data['version_events'] ?? null) || $data['version_events'] !== []
            || !is_array($data['census_snapshot'] ?? null)
            || !is_array($data['delegation'] ?? null)
            || !is_array($data['case'] ?? null)
            || !is_array($data['gates'] ?? null)) {
            throw new RuntimeException('Normative registry envelope mismatch.');
        }

        $instruments = [
            'IDN-PERMENDAGRI-86-2017' => [
                '86', 2017,
                'https://peraturan.bpk.go.id/Details/311927/permendagri-no-86-tahun-2017',
                'https://peraturan.go.id/files/bn1312-2017bt.pdf', '2017-09-25',
            ],
            'IDN-PERMENDAGRI-10-2025' => [
                '10', 2025,
                'https://peraturan.bpk.go.id/Details/321470/permendagri-no-10-tahun-2025',
                'https://peraturan.go.id/files/permendagri-no-10-tahun-2025.pdf', '2025-06-23',
            ],
        ];
        $seen = [];
        foreach ($data['instruments'] as $doc) {
            if (!is_array($doc) || !isset($instruments[$doc['id'] ?? ''])
                || isset($seen[$doc['id']])) {
                throw new RuntimeException('Unexpected or duplicated regulation.');
            }
            [$number, $year, $metadata, $pdf, $effective] = $instruments[$doc['id']];
            if (($doc['kind'] ?? null) !== 'PERMENDAGRI'
                || ($doc['number'] ?? null) !== $number
                || ($doc['year'] ?? null) !== $year
                || ($doc['official_metadata_url'] ?? null) !== $metadata
                || ($doc['official_pdf_url'] ?? null) !== $pdf
                || !array_key_exists('effective_date', $doc)
                || $doc['effective_date'] !== $effective
                || !array_key_exists('pdf_sha256', $doc) || $doc['pdf_sha256'] !== null
                || ($doc['pdf_snapshot_status'] ?? null) !== 'NOT_CAPTURED_NO_HASH'
                || ($doc['amendment_census'] ?? null) !== 'NOT_EXHAUSTIVELY_VERIFIED'
                || !is_string($doc['title'] ?? null) || trim($doc['title']) === '') {
                throw new RuntimeException('Regulation source/version mismatch.');
            }
            $seen[$doc['id']] = true;
        }

        $provisions = [
            'ROOT-86-2017-ART76' => ['IDN-PERMENDAGRI-86-2017', 'Pasal 76; halaman cetak -40-', 'DELEGATION_FOR_ANNUAL_RKPD_GUIDELINE', 'Pedoman penyusunan RKPD yang dirujuk Pasal 75 ditetapkan dengan Peraturan Menteri.'],
            'ROOT-10-2025-PREAMBLE-B' => ['IDN-PERMENDAGRI-10-2025', 'Menimbang huruf b; halaman cetak 1', 'EXPLICIT_LEGAL_BASIS_REFERENCE', 'Konsiderans Permendagri 10/2025 menyatakan pelaksanaan Pasal 76 Permendagri 86/2017.'],
            'ROOT-10-2025-ART2-3' => ['IDN-PERMENDAGRI-10-2025', 'Pasal 2 ayat (3); halaman cetak -4-', 'SIMULTANEOUS_DRAFTING_REQUIREMENT', 'Penyusunan RKPD 2026 dilakukan simultan dan terkoordinasi dengan penyusunan RPJMD 2025–2029.'],
            'ROOT-10-2025-ART3-5' => ['IDN-PERMENDAGRI-10-2025', 'Pasal 3 ayat (5); halaman cetak -5-', 'MUNICIPAL_RKPD_NATIONAL_AND_PROVINCIAL_REFERENCE', 'RKPD kabupaten/kota 2026 berpedoman pada RKP 2026, program strategis nasional, serta RKPD provinsi 2026.'],
            'ROOT-10-2025-ART2-6' => ['IDN-PERMENDAGRI-10-2025', 'Pasal 2 ayat (6); halaman cetak -5-', 'PROVINCIAL_DIY_EXCEPTION', 'Ketentuan RPJMD DIY 2022–2027 secara khusus menyebut Provinsi Daerah Istimewa Yogyakarta, bukan otomatis Kota Yogyakarta.'],
            'ROOT-10-2025-ART12-2' => ['IDN-PERMENDAGRI-10-2025', 'Pasal 12 ayat (2) huruf a–c; halaman cetak -8-', 'MUNICIPAL_RKPD_TO_BUDGET_PROCESS', 'RKPD kabupaten/kota 2026 menjadi pedoman rancangan KUA–PPAS, sinkronisasi rancangan APBD 2026, dan evaluasi pembangunan daerah.'],
        ];
        $seen = [];
        foreach ($data['provisions'] as $provision) {
            if (!is_array($provision) || !isset($provisions[$provision['id'] ?? ''])
                || isset($seen[$provision['id']])) {
                throw new RuntimeException('Unexpected or duplicated provision.');
            }
            [$docId, $locator, $role, $summary] = $provisions[$provision['id']];
            if (($provision['instrument_id'] ?? null) !== $docId
                || ($provision['locator'] ?? null) !== $locator
                || ($provision['role'] ?? null) !== $role
                || ($provision['summary'] ?? null) !== $summary) {
                throw new RuntimeException('Normative provision evidence mismatch.');
            }
            $seen[$provision['id']] = true;
        }

        $census = $data['census_snapshot'];
        if (($census['schema'] ?? null) !== 'PI_R70N_OFFICIAL_RELATION_METADATA_CENSUS_V1'
            || ($census['as_of_date'] ?? null) !== '2026-11-08'
            || ($census['scope'] ?? null) !== 'TWO_SCOPED_PARENT_PERMENDAGRI_ONLY'
            || ($census['method'] ?? null) !== 'OFFICIAL_JDIH_BPK_METADATA_AND_NATIONAL_REGULATION_TEXT'
            || ($census['exhaustiveness'] ?? null) !== 'NOT_EXHAUSTIVELY_VERIFIED'
            || ($census['official_pdf_bytes_sha256'] ?? null) !== 'NOT_CAPTURED'
            || ($census['result'] ?? null) !== 'AMENDMENT_CHAIN_INCOMPLETE'
            || ($census['empty_event_list_is_not_no_amendment_claim'] ?? null) !== true
            || ($census['admitted_direct_amendment_events'] ?? null) !== []
            || !is_array($census['observations'] ?? null)
            || count($census['observations']) !== 2
            || $census['observations'] !== [['instrument_id' => 'IDN-PERMENDAGRI-86-2017', 'official_current_status' => 'BERLAKU', 'official_effective_date' => '2017-09-25', 'official_metadata_url' => 'https://peraturan.bpk.go.id/Details/311927/permendagri-no-86-tahun-2017', 'relation_catalogue' => 'MENCABUT_PERMENDAGRI_54_2010', 'relation_provision' => 'Ketentuan penutup; periksa naskah sumber', 'related_instrument_id' => 'IDN-PERMENDAGRI-54-2010', 'related_source_url' => 'https://peraturan.go.id/files/bn517-2010.pdf', 'direct_amendment_record' => 'NONE_LISTED_NOT_PROOF_OF_NONE'], ['instrument_id' => 'IDN-PERMENDAGRI-10-2025', 'official_current_status' => 'BERLAKU', 'official_effective_date' => '2025-06-23', 'official_metadata_url' => 'https://peraturan.bpk.go.id/Details/321470/permendagri-no-10-tahun-2025', 'relation_catalogue' => 'BELUM_TERSEDIA', 'relation_provision' => null, 'related_instrument_id' => null, 'related_source_url' => null, 'direct_amendment_record' => 'UNDETERMINED']]) {
            throw new RuntimeException('Official amendment census observation mismatch.');
        }

        $delegation = [
            'from_provision_id' => 'ROOT-86-2017-ART76',
            'to_instrument_id' => 'IDN-PERMENDAGRI-10-2025',
            'support_provision_id' => 'ROOT-10-2025-PREAMBLE-B',
            'type' => 'EXPLICIT_NORMATIVE_DELEGATION_REFERENCE',
            'evidence_status' => 'SOURCE_CONFIRMED_FOR_TEXTUAL_REFERENCE',
            'legal_validation' => 'REVIEW_REQUIRED',
        ];
        $case = [
            'planning_document_id' => 'ID-DIY-YOG-RKPD-2026-PERWALI-34-2025',
            'planning_document_kind' => 'RKPD',
            'planning_document_source' => 'https://jdih.jogjakota.go.id/asset/naskah/2025pw3405034.pdf',
            'planning_year' => 2026,
            'municipal_relevance_provision_id' => 'ROOT-10-2025-ART3-5',
            'simultaneous_drafting_provision_id' => 'ROOT-10-2025-ART2-3',
            'regional_exception_provision_id' => 'ROOT-10-2025-ART2-6',
            'regional_exception_applicability' => 'PROVINCE_DIY_ONLY_NOT_CITY_YOGYAKARTA',
            'normative_applicability' => 'SOURCE_BOUND_RELEVANCE_CANDIDATE',
            'actual_compliance' => 'NOT_ASSESSED',
            'admitted_alignment' => null,
        ];
        $gates = [
            'normative_root_trace' => 'PARTIAL_PRIMARY_PROVISIONS_FOUND',
            'regulatory_version' => 'AMENDMENT_CHAIN_INCOMPLETE',
            'planning_constraint_crosswalk' => 'MAPPING_CANDIDATE',
            'fiscal_legal_basis' => 'NOT_ASSESSED',
            'source_pdf_hash' => 'NOT_CAPTURED',
            'legal_harmonization' => 'NOT_ASSESSED',
            'substantive_alignment' => 'NOT_ASSESSED',
            'numeric_comparison' => 'BLOCKED',
            'review_status' => 'REVIEW_REQUIRED',
            'production_admission' => 'DENIED',
            'result' => 'ABSTAIN',
        ];
        foreach (['delegation' => $delegation, 'case' => $case, 'gates' => $gates] as $key => $expected) {
            if ($data[$key] !== $expected) {
                throw new RuntimeException('Normative '.$key.' guard mismatch.');
            }
        }
    }
}
