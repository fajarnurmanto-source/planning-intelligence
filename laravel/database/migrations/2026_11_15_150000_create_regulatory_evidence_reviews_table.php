<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_evidence_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id', 64);
            $table->string('candidate_id', 96);
            $table->string('review_dimension', 32);
            $table->string('decision', 32);
            $table->text('reason');
            $table->char('source_manifest_sha256', 64);
            $table->char('candidate_sha256', 64);
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['tenant_id', 'candidate_id', 'review_dimension'], 'r84_review_history_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulatory_evidence_reviews');
    }
};
