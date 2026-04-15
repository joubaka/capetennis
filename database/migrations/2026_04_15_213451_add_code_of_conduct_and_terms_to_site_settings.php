<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_settings')->insert([
            'key'        => 'require_code_of_conduct',
            'value'      => '0',
            'label'      => 'Require Code of Conduct',
            'group'      => 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_settings')->insert([
            'key'        => 'require_terms',
            'value'      => '0',
            'label'      => 'Require Terms & Conditions',
            'group'      => 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('site_settings')->whereIn('key', [
            'require_code_of_conduct',
            'require_terms',
        ])->delete();
    }
};
