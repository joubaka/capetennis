<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('masters_invitations', function (Blueprint $table) {
            $table->unsignedBigInteger('declined_by_user_id')->nullable()->after('decline_reason')->index();
            $table->string('decline_method', 50)->nullable()->after('declined_by_user_id');
            $table->unsignedBigInteger('admin_removed_by_user_id')->nullable()->after('decline_method')->index();
            $table->timestamp('admin_removed_at')->nullable()->after('admin_removed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('masters_invitations', function (Blueprint $table) {
            $table->dropColumn(['declined_by_user_id', 'decline_method', 'admin_removed_by_user_id', 'admin_removed_at']);
        });
    }
};
