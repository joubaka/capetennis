<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('masters_invitation_batches', function (Blueprint $table) {
            $table->boolean('public_list_published')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('masters_invitation_batches', function (Blueprint $table) {
            $table->dropColumn('public_list_published');
        });
    }
};
