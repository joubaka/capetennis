<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('eventtypes')) return;

        if (!Schema::hasColumn('eventtypes', 'code')) {
            Schema::table('eventtypes', function (Blueprint $table) {
                $table->string('code', 40)->nullable()->index()->after('type');
            });
        }

        DB::table('eventtypes')->whereNull('code')->update([
            'code' => DB::raw("CASE WHEN type = 1 THEN 'individual' WHEN type = 2 THEN 'team' WHEN type = 3 THEN 'camp' ELSE NULL END"),
        ]);

        $exists = DB::table('eventtypes')->where('code', 'masters')->exists();
        if (!$exists) {
            $nextId = max(4, ((int) DB::table('eventtypes')->max('id')) + 1);
            DB::table('eventtypes')->insert([
                'id' => $nextId,
                'name' => 'Masters',
                'type' => 1,
                'code' => 'masters',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('eventtypes')) return;
        DB::table('eventtypes')->where('code', 'masters')->delete();
        if (Schema::hasColumn('eventtypes', 'code')) {
            Schema::table('eventtypes', fn (Blueprint $table) => $table->dropColumn('code'));
        }
    }
};
