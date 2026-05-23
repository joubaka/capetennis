<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add canonical pipeline columns to series_rankings
        if (Schema::hasTable('series_rankings')) {
            Schema::table('series_rankings', function (Blueprint $table) {
            if (!Schema::hasColumn('series_rankings', 'status')) {
                $table->string('status', 20)->default('calculated')->after('meta_json')->index();
            }
            if (!Schema::hasColumn('series_rankings', 'ranking_list_id')) {
                $table->unsignedBigInteger('ranking_list_id')->nullable()->after('series_id')->index();
            }
            if (!Schema::hasColumn('series_rankings', 'run_id')) {
                $table->string('run_id', 64)->nullable()->after('status')->index();
            }
            if (!Schema::hasColumn('series_rankings', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable();
            }
            if (!Schema::hasColumn('series_rankings', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
            if (!Schema::hasColumn('series_rankings', 'published_by')) {
                $table->unsignedBigInteger('published_by')->nullable();
            }
            if (!Schema::hasColumn('series_rankings', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }
        });
        }

        // Create ranking_audit_logs table
        if (!Schema::hasTable('ranking_audit_logs')) {
            Schema::create('ranking_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('series_id')->index();
                $table->string('run_id', 64)->nullable()->index();
                $table->string('action', 80);
                $table->longText('payload')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ranking_audit_logs');

        Schema::table('series_rankings', function (Blueprint $table) {
            foreach (['status', 'ranking_list_id', 'run_id', 'reviewed_by', 'reviewed_at', 'published_by', 'published_at'] as $col) {
                if (Schema::hasColumn('series_rankings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
