<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->nullable()->unique();
            $table->unsignedBigInteger('event_id')->index();
            $table->unsignedBigInteger('category_event_id')->nullable()->index();
            $table->unsignedBigInteger('fixture_id')->nullable()->index();
            $table->unsignedBigInteger('player_id')->index();
            $table->unsignedBigInteger('reported_by')->index();
            $table->unsignedBigInteger('triaged_by')->nullable()->index();
            $table->string('status', 40)->default('submitted')->index();
            $table->string('severity', 20)->default('standard')->index();
            $table->string('incident_location')->nullable();
            $table->dateTime('incident_at')->index();
            $table->text('summary');
            $table->text('player_response')->nullable();
            $table->dateTime('response_due_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->string('rulebook_version')->nullable();
            $table->text('closure_reason')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'status']);
            $table->index(['player_id', 'status']);
        });

        Schema::create('disciplinary_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('violation_type_id')->nullable()->index();
            $table->string('rule_code')->nullable();
            $table->string('rule_title');
            $table->text('allegation');
            $table->string('finding', 30)->default('pending')->index();
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();
        });

        Schema::create('disciplinary_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('submitted_by')->index();
            $table->string('kind', 30)->default('statement');
            $table->string('title');
            $table->text('statement')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('visibility', 20)->default('panel');
            $table->timestamps();
        });

        Schema::create('disciplinary_case_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 20)->default('member');
            $table->boolean('conflict_declared')->default(false);
            $table->text('conflict_notes')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('recused_at')->nullable();
            $table->timestamps();
            $table->unique(['disciplinary_case_id', 'user_id'], 'disciplinary_case_panel_unique');
        });

        Schema::create('disciplinary_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('decided_by')->index();
            $table->string('outcome', 30)->index();
            $table->longText('reasons');
            $table->json('panel_snapshot');
            $table->json('rule_snapshot')->nullable();
            $table->dateTime('decided_at');
            $table->dateTime('served_at')->nullable();
            $table->timestamps();
        });

        Schema::create('disciplinary_sanctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->restrictOnDelete();
            $table->foreignId('disciplinary_decision_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('player_id')->index();
            $table->string('type', 30)->index();
            $table->string('scope', 20)->default('global')->index();
            $table->unsignedBigInteger('scope_id')->nullable()->index();
            $table->unsignedInteger('points')->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->text('details')->nullable();
            $table->boolean('stayed')->default(false)->index();
            $table->dateTime('revoked_at')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->index(['player_id', 'starts_at', 'ends_at'], 'disciplinary_sanction_active_idx');
        });

        Schema::create('disciplinary_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('submitted_by')->index();
            $table->longText('grounds');
            $table->string('status', 30)->default('submitted')->index();
            $table->longText('outcome_reasons')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable()->index();
            $table->dateTime('submitted_at');
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('disciplinary_case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disciplinary_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action', 80)->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        if (Schema::hasTable('player_violations') && ! Schema::hasColumn('player_violations', 'disciplinary_case_id')) {
            Schema::table('player_violations', function (Blueprint $table) {
                $table->unsignedBigInteger('disciplinary_case_id')->nullable()->unique()->after('event_id');
                $table->dateTime('voided_at')->nullable()->after('disciplinary_case_id');
                $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
                $table->text('void_reason')->nullable()->after('voided_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('player_violations') && Schema::hasColumn('player_violations', 'disciplinary_case_id')) {
            Schema::table('player_violations', fn (Blueprint $table) => $table->dropColumn([
                'disciplinary_case_id', 'voided_at', 'voided_by', 'void_reason',
            ]));
        }

        Schema::dropIfExists('disciplinary_case_events');
        Schema::dropIfExists('disciplinary_appeals');
        Schema::dropIfExists('disciplinary_sanctions');
        Schema::dropIfExists('disciplinary_decisions');
        Schema::dropIfExists('disciplinary_case_assignments');
        Schema::dropIfExists('disciplinary_evidence');
        Schema::dropIfExists('disciplinary_charges');
        Schema::dropIfExists('disciplinary_cases');
    }
};
