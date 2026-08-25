<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('eventtypes')) return;
        $masters = DB::table('eventtypes')->where('code', 'masters')->first();
        if (!$masters || (int) $masters->id >= 4) return;

        $targetId = max(4, ((int) DB::table('eventtypes')->max('id')) + 1);
        DB::transaction(function () use ($masters, $targetId) {
            DB::table('eventtypes')->where('id', $masters->id)->update(['id' => $targetId]);
            DB::table('events')->where('eventType', $masters->id)->update(['eventType' => $targetId]);
        });
    }

    public function down(): void
    {
        // Identity repair is intentionally not reversed; event type IDs are referenced by events.
    }
};
