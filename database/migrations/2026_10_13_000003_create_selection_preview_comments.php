<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selection_preview_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('group_key', 40);
            $table->string('player_key', 100);
            $table->foreignId('author_id')->constrained('users');
            $table->unsignedInteger('draft_version');
            $table->text('body');
            $table->timestamp('created_at');
            $table->index(['event_id', 'group_key', 'player_key', 'id'], 'preview_comment_thread_index');
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('selection_preview_comments')->exists()) {
            throw new RuntimeException('Cannot remove recorded selection discussion history.');
        }
        Schema::dropIfExists('selection_preview_comments');
    }
};
