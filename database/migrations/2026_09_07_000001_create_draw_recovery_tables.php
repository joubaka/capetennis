<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('draw_recovery_cases')) {
            Schema::create('draw_recovery_cases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('draw_id')->index();
                $table->unsignedBigInteger('source_fixture_id')->nullable()->index();
                $table->unsignedBigInteger('requested_by')->nullable()->index();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->unsignedBigInteger('restored_by')->nullable()->index();
                $table->string('operation', 64);
                $table->string('status', 32)->default('previewed')->index();
                $table->text('reason');
                $table->text('restore_reason')->nullable();
                $table->json('impact')->nullable();
                $table->char('preview_fingerprint', 64);
                $table->unsignedBigInteger('before_snapshot_id')->nullable();
                $table->unsignedBigInteger('after_snapshot_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamp('restored_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('draw_recovery_snapshots')) {
            Schema::create('draw_recovery_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('draw_id')->index();
                $table->unsignedBigInteger('recovery_case_id')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->string('kind', 32);
                $table->char('checksum', 64)->index();
                $table->longText('payload');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_recovery_snapshots');
        Schema::dropIfExists('draw_recovery_cases');
    }
};
