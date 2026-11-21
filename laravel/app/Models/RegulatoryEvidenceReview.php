<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'candidate_id',
    'review_dimension',
    'decision',
    'reason',
    'source_manifest_sha256',
    'candidate_sha256',
    'reviewer_id',
    'decided_at',
])]
final class RegulatoryEvidenceReview extends Model
{
    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
