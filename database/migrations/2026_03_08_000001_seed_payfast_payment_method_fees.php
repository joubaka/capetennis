<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $methods = [
            'credit_card' => ['label' => 'Credit Card', 'percentage' => '2.90'],
            'debit_card'  => ['label' => 'Debit Card',  'percentage' => '2.90'],
            'eft'         => ['label' => 'EFT',          'percentage' => '1.85'],
            'apple_pay'   => ['label' => 'Apple Pay',    'percentage' => '3.05'],
            'samsung_pay' => ['label' => 'Samsung Pay',  'percentage' => '3.05'],
            'zapper'      => ['label' => 'Zapper',       'percentage' => '3.05'],
        ];

        foreach ($methods as $method => $config) {
            DB::table('site_settings')->insert([
                'key'        => "payfast_fee_pct_{$method}",
                'value'      => $config['percentage'],
                'label'      => "{$config['label']} Fee %",
                'group'      => 'payfast',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->where('key', 'like', 'payfast_fee_pct_%')
            ->delete();
    }
};
