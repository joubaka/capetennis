<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_region_financial_recipient_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->foreignId('event_region_id')->constrained('event_regions', indexName: 'fin_recipient_scope_fk')->restrictOnDelete();
            $table->unsignedBigInteger('region_id');
            $table->foreignId('source_manager_user_id')->constrained('users', indexName: 'fin_recipient_source_fk')->restrictOnDelete();
            $table->foreignId('target_convenor_id')->constrained('event_convenors', indexName: 'fin_recipient_director_fk')->restrictOnDelete();
            $table->foreignId('target_user_id')->constrained('users', indexName: 'fin_recipient_target_user_fk')->restrictOnDelete();
            $table->string('source_name');
            $table->string('target_name');
            $table->unsignedBigInteger('confirmed_by');
            $table->string('confirmed_by_name');
            $table->text('evidence');
            $table->uuid('request_token')->unique();
            $table->char('request_fingerprint', 64);
            $table->timestamp('confirmed_at');
            $table->unique(['event_id', 'region_id'], 'financial_recipient_event_region_unique');
            $table->unique('event_region_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_region_financial_recipient_links');
    }
};
