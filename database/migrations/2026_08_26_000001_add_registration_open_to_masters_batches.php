<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('masters_invitation_batches', function (Blueprint $table) {
            $table->boolean('registration_open')->default(false)->after('public_list_published');
        });
    }

    public function down(): void
    {
        Schema::table('masters_invitation_batches', function (Blueprint $table) {
            $table->dropColumn('registration_open');
        });
    }
};
