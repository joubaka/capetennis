<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('violation_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('category', ['on_court', 'withdrawal', 'no_show', 'abuse']);
            $table->unsignedInteger('default_points');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seed default violation types
        DB::table('violation_types')->insert([
            [
                'name'           => 'Code Violation (On-Court)',
                'category'       => 'on_court',
                'default_points' => 2,
                'description'    => 'Unsportsmanlike conduct, ball abuse, racket abuse, verbal abuse, etc.',
                'active'         => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'name'           => 'Late Withdrawal',
                'category'       => 'withdrawal',
                'default_points' => 3,
                'description'    => 'Withdrawing from an event after the entry deadline.',
                'active'         => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'name'           => 'No Show',
                'category'       => 'no_show',
                'default_points' => 5,
                'description'    => 'Failing to appear for a scheduled match without notification.',
                'active'         => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'name'           => 'Physical / Gross Misconduct',
                'category'       => 'abuse',
                'default_points' => 8,
                'description'    => 'Physical abuse, threatening behaviour, or gross unsportsmanlike conduct.',
                'active'         => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_types');
    }
};
