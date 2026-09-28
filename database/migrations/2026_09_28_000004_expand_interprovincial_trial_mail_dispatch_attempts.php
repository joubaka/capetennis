<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interprovincial_trial_mail_dispatches', function (Blueprint $table): void {
            $table->uuid('request_token')->nullable()->after('invitation_id');
            $table->string('kind', 24)->default('initial')->after('request_token');
            $table->unsignedBigInteger('requested_by_user_id')->nullable()->after('kind');
        });

        DB::table('interprovincial_trial_mail_dispatches')->orderBy('id')->eachById(function ($dispatch): void {
            $hex = md5('interpro-initial-dispatch-'.$dispatch->id);
            $token = substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-5'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
            DB::table('interprovincial_trial_mail_dispatches')->where('id', $dispatch->id)->update([
                'request_token' => $token,
                'kind' => 'initial',
            ]);
        });

        Schema::table('interprovincial_trial_mail_dispatches', function (Blueprint $table): void {
            $table->dropUnique('interprovincial_trial_mail_dispatches_invitation_id_unique');
            $table->unique(['invitation_id', 'request_token'], 'interpro_dispatch_invitation_request_unique');
            $table->index(['invitation_id', 'kind'], 'interpro_dispatch_invitation_kind_index');
        });
    }

    public function down(): void
    {
        if (DB::table('interprovincial_trial_mail_dispatches')->where('kind', '!=', 'initial')->exists()) {
            throw new RuntimeException('Cannot roll back dispatch-attempt support while follow-up invitation audit rows exist.');
        }
        Schema::table('interprovincial_trial_mail_dispatches', function (Blueprint $table): void {
            $table->dropUnique('interpro_dispatch_invitation_request_unique');
            $table->dropIndex('interpro_dispatch_invitation_kind_index');
            $table->unique('invitation_id');
            $table->dropColumn(['request_token', 'kind', 'requested_by_user_id']);
        });
    }
};
