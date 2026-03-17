<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('label')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        // Seed default PayFast fee percentage (3.2%)
        DB::table('site_settings')->insert([
            'key'        => 'payfast_fee_percentage',
            'value'      => '3.2',
            'label'      => 'PayFast Fee Percentage',
            'group'      => 'payfast',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed default PayFast flat fee (R2.00)
        DB::table('site_settings')->insert([
            'key'        => 'payfast_fee_flat',
            'value'      => '2.00',
            'label'      => 'PayFast Flat Fee (R)',
            'group'      => 'payfast',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed VAT rate (14%)
        DB::table('site_settings')->insert([
            'key'        => 'payfast_vat_rate',
            'value'      => '14',
            'label'      => 'VAT Rate (%)',
            'group'      => 'payfast',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
