<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('eventtypes')
            ->whereIn(DB::raw('LOWER(name)'), ['interpro trials', 'interprovincial trials'])
            ->update(['code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);

        if (Schema::hasTable('event_nominations')) {
            DB::table('event_nominations')->orderBy('id')->eachById(function ($nomination): void {
                $eventId = DB::table('category_events')->where('id', $nomination->category_event_id)->value('event_id');
                if ($eventId && (int) $nomination->event_id !== (int) $eventId) {
                    DB::table('event_nominations')->where('id', $nomination->id)->update(['event_id' => $eventId]);
                }
            });
            DB::statement('DELETE n1 FROM event_nominations n1 INNER JOIN event_nominations n2 ON n1.category_event_id = n2.category_event_id AND n1.player_id = n2.player_id AND n1.id > n2.id');
            Schema::table('event_nominations', function (Blueprint $table): void {
                $table->unique(['category_event_id', 'player_id'], 'event_nominations_category_player_unique');
            });
        }

        Schema::create('interprovincial_trial_invitation_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->index();
            $table->string('status')->default('draft');
            $table->unsignedInteger('snapshot_version')->default(1);
            $table->string('snapshot_hash', 64);
            $table->foreignId('created_by_user_id');
            $table->foreignId('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('interprovincial_trial_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->index();
            $table->foreignId('event_id')->index();
            $table->foreignId('category_event_id')->index();
            $table->foreignId('nomination_id');
            $table->foreignId('player_id')->index();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('status')->default('prepared');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'nomination_id'], 'interpro_trial_batch_nomination_unique');
        });

        Schema::create('interprovincial_trial_mail_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invitation_id')->unique();
            $table->foreignId('bulk_email_log_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interprovincial_trial_mail_dispatches');
        Schema::dropIfExists('interprovincial_trial_invitations');
        Schema::dropIfExists('interprovincial_trial_invitation_batches');
        if (Schema::hasTable('event_nominations')) {
            Schema::table('event_nominations', fn (Blueprint $table) => $table->dropUnique('event_nominations_category_player_unique'));
        }
    }
};
