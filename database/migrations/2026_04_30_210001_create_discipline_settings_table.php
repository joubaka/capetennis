<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('label');
            $table->timestamps();
        });

        DB::table('discipline_settings')->insert([
            [
                'key'        => 'suspension_threshold',
                'value'      => '12',
                'label'      => 'Suspension Threshold (points)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'expiry_days',
                'value'      => '365',
                'label'      => 'Points Expiry (days)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'first_suspension_months',
                'value'      => '3',
                'label'      => '1st Suspension Duration (months)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key'        => 'second_suspension_months',
                'value'      => '6',
                'label'      => '2nd+ Suspension Duration (months)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_settings');
    }
};
