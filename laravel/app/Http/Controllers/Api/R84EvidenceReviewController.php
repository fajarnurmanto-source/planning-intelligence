<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegulatoryEvidenceReview;
use App\Services\Pi\Regulatory\R82NationalEvidencePipeline;
use App\Services\Pi\Regulatory\R82NationalSourceVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

/** R84: append-only human decisions; never promotes a candidate to the graph. */
final class R84EvidenceReviewController extends Controller
{
    private const DIMENSIONS = ['CITATION_ACCURACY', 'LEGAL_RELATION', 'HARMONIZATION'];

    private const DECISIONS = ['ACCEPTED', 'REJECTED', 'REVISION_REQUIRED'];

    public function index(Request $request): JsonResponse
    {
        $candidateId = $request->query('candidate_id');
        if ($candidateId !== null && (! is_string($candidateId) || ! $this->candidateIdAllowed($candidateId))) {
            abort(404);
        }

        $history = RegulatoryEvidenceReview::query()
            ->where('tenant_id', (string) $request->user()->tenant_id)
            ->when($candidateId, fn ($query) => $query->where('candidate_id', $candidateId))
            ->with('reviewer:id,username')
            ->orderBy('id')
            ->get();

        return response()->json([
            'schema' => 'PI_R84_CONTROLLED_RELATION_REVIEW_V1',
            'tenant_id' => $request->user()->tenant_id,
            'history' => $history,
            'relation_promotion' => 'NOT_PERFORMED',
            'legal_harmonization' => 'NOT_AUTOMATED',
            'production_admission' => 'DENIED',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(
        Request $request,
        R82NationalEvidencePipeline $pipeline,
        R82NationalSourceVerifier $verifier
    ): JsonResponse {
        if (! in_array($request->user()->role, ['reviewer', 'institution_admin'], true)) {
            abort(403);
        }

        $input = $request->validate([
            'candidate_id' => ['required', 'string', Rule::in($this->candidateIds())],
            'review_dimension' => ['required', 'string', Rule::in(self::DIMENSIONS)],
            'decision' => ['required', 'string', Rule::in(self::DECISIONS)],
            'reason' => ['required', 'string', 'min:12', 'max:2000'],
            'source_manifest_sha256' => ['required', 'string', 'regex:/\A[A-F0-9]{64}\z/'],
            'candidate_sha256' => ['required', 'string', 'regex:/\A[A-F0-9]{64}\z/'],
        ]);

        try {
            $sources = $verifier->verify($pipeline->snapshotDirectory());
            $report = $this->loadReport($pipeline->outputPath());
            if (($report['r81_manifest_sha256'] ?? null) !== $sources['manifest_sha256']
                || $input['source_manifest_sha256'] !== $sources['manifest_sha256']) {
                throw new RuntimeException('R84_SOURCE_MANIFEST_DRIFT');
            }

            $candidate = collect($report['candidates'])->firstWhere('target_provision_id', $input['candidate_id']);
            if (! is_array($candidate)
                || ($candidate['review_status'] ?? null) !== 'REVIEW_REQUIRED'
                || ($candidate['relation_admission'] ?? null) !== 'NOT_PERFORMED'
                || $input['candidate_sha256'] !== $this->candidateHash($candidate)) {
                throw new RuntimeException('R84_CANDIDATE_DRIFT');
            }

            $review = DB::transaction(fn () => RegulatoryEvidenceReview::query()->create([
                ...$input,
                'tenant_id' => (string) $request->user()->tenant_id,
                'reviewer_id' => $request->user()->getKey(),
                'decided_at' => now(),
            ]));

            return response()->json([
                'schema' => 'PI_R84_CONTROLLED_RELATION_REVIEW_V1',
                'review' => $review,
                'relation_promotion' => 'NOT_PERFORMED',
                'production_admission' => 'DENIED',
            ], 201)->header('Cache-Control', 'private, no-store');
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'status' => 'ABSTAIN',
                'code' => 'R84_REVIEW_SOURCE_UNAVAILABLE_OR_CHANGED',
                'relation_promotion' => 'DENIED',
                'production_admission' => 'DENIED',
            ], 503)->header('Cache-Control', 'private, no-store');
        }
    }

    /** @return array<string, mixed> */
    private function loadReport(string $path): array
    {
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 512 * 1024) {
            throw new RuntimeException('R84_REVIEW_REPORT_NOT_PREPARED');
        }
        $report = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($report)
            || ($report['schema'] ?? null) !== 'PI_R82_NATIONAL_PASSAGE_CANDIDATES_QA_V1'
            || ($report['tenant_id'] ?? null) !== config('pi.tenant_id')
            || ! is_array($report['candidates'] ?? null)
            || count($report['candidates']) !== 7
            || ($report['relation_admission'] ?? null) !== 'NOT_PERFORMED'
            || ($report['production_admission'] ?? null) !== 'DENIED') {
            throw new RuntimeException('R84_REVIEW_REPORT_REJECTED');
        }

        return $report;
    }

    /** @param array<string, mixed> $candidate */
    private function candidateHash(array $candidate): string
    {
        return strtoupper(hash('sha256', json_encode($candidate, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
    }

    /** @return list<string> */
    private function candidateIds(): array
    {
        return array_map(fn (int $article) => 'UU-18-2008:PASAL-'.$article, [11, 16, 20, 22, 24, 25, 28]);
    }

    private function candidateIdAllowed(string $candidateId): bool
    {
        return in_array($candidateId, $this->candidateIds(), true);
    }
}
