<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('series', 'ranking_review_default_hours')) {
            Schema::table('series', function (Blueprint $table): void {
                $table->unsignedSmallInteger('ranking_review_default_hours')->default(24);
            });
        }

        if (! Schema::hasTable('ranking_review_campaigns')) {
            $seriesIdType = strtolower(Schema::getColumnType('series', 'id', true));

            Schema::create('ranking_review_campaigns', function (Blueprint $table) use ($seriesIdType): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $this->addMatchingSeriesId($table, $seriesIdType);
                $table->foreign('series_id')->references('id')->on('series')->cascadeOnDelete();
                $table->string('run_id', 100);
                $table->string('snapshot_hash', 64);
                $table->string('status', 32)->default('queued')->index();
                $table->string('subject');
                $table->text('message');
                $table->string('reply_to');
                $table->timestamp('cutoff_at');
                $table->unsignedBigInteger('sent_by');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->unsignedBigInteger('finalized_by')->nullable();
                $table->unsignedInteger('player_count')->default(0);
                $table->unsignedInteger('recipient_count')->default(0);
                $table->unsignedInteger('missing_email_count')->default(0);
                $table->timestamps();

                $table->unique(['series_id', 'run_id'], 'ranking_review_campaign_run_unique');
                $table->index(['series_id', 'status']);
            });
        }

        if (! Schema::hasTable('ranking_review_recipients')) {
            Schema::create('ranking_review_recipients', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('ranking_review_campaign_id')
                    ->constrained('ranking_review_campaigns', indexName: 'ranking_review_recipient_campaign_fk')
                    ->cascadeOnDelete();
                $table->string('email')->nullable();
                $table->json('player_ids');
                $table->json('player_names');
                $table->json('category_names')->nullable();
                $table->string('status', 32)->default('queued')->index();
                $table->unsignedBigInteger('bulk_email_log_id')->nullable()->index();
                $table->string('error_message')->nullable();
                $table->timestamps();

                $table->index(['ranking_review_campaign_id', 'email'], 'ranking_review_recipient_email_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_review_recipients');
        Schema::dropIfExists('ranking_review_campaigns');

        Schema::table('series', function (Blueprint $table): void {
            if (Schema::hasColumn('series', 'ranking_review_default_hours')) {
                $table->dropColumn('ranking_review_default_hours');
            }
        });
    }

    private function addMatchingSeriesId(Blueprint $table, string $seriesIdType): void
    {
        $unsigned = str_contains($seriesIdType, 'unsigned');

        if (str_contains($seriesIdType, 'bigint')) {
            $table->bigInteger('series_id', false, $unsigned);

            return;
        }

        if (str_contains($seriesIdType, 'smallint')) {
            $table->smallInteger('series_id', false, $unsigned);

            return;
        }

        if (str_contains($seriesIdType, 'mediumint')) {
            $table->mediumInteger('series_id', false, $unsigned);

            return;
        }

        $table->integer('series_id', false, $unsigned);
    }
};
