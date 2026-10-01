<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('trial_participations',function(Blueprint $t){$t->id();$t->unsignedBigInteger('event_id');$t->unsignedBigInteger('player_id');$t->unsignedBigInteger('slot_id');$t->unsignedBigInteger('order_id')->nullable()->unique();$t->unsignedBigInteger('payer_id')->nullable();$t->timestamp('paid_at')->nullable();$t->timestamps();$t->unique(['event_id','player_id']);});
        Schema::create('trial_participation_proofs',function(Blueprint $t){$t->id();$t->unsignedBigInteger('participation_id')->index();$t->unsignedBigInteger('order_id')->index();$t->unsignedBigInteger('payer_id');$t->string('path');$t->string('mime_type',100);$t->unsignedInteger('size');$t->string('status')->default('pending');$t->unsignedBigInteger('reviewed_by')->nullable();$t->timestamp('reviewed_at')->nullable();$t->timestamps();});
        Schema::create('trial_participation_receipts',function(Blueprint $t){$t->id();$t->unsignedBigInteger('participation_id')->unique();$t->unsignedBigInteger('order_id')->unique();$t->unsignedBigInteger('event_id')->index();$t->decimal('amount',12,2);$t->string('method',20);$t->string('reference',120);$t->unsignedBigInteger('verified_by');$t->unsignedBigInteger('proof_id')->nullable();$t->timestamp('paid_at');$t->timestamps();});
    }
    public function down(): void {
        foreach(['trial_participation_receipts','trial_participation_proofs','trial_participations'] as $table) {
            if (\Illuminate\Support\Facades\DB::table($table)->exists()) throw new RuntimeException('Retain participation payment and audit history before rollback.');
        }
        foreach(['trial_participation_receipts','trial_participation_proofs','trial_participations'] as $table) Schema::dropIfExists($table);
    }
};
