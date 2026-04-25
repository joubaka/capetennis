<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('label', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // Seed default types (matches the former hardcoded list)
        $types = [
            ['key' => 'balls',           'label' => 'Balle',              'sort_order' => 10, 'is_system' => false],
            ['key' => 'venue',           'label' => 'Bane (Venue)',        'sort_order' => 20, 'is_system' => false],
            ['key' => 'convenors',       'label' => 'Convenors',           'sort_order' => 30, 'is_system' => false],
            ['key' => 'medals',          'label' => 'Medalies',            'sort_order' => 40, 'is_system' => false],
            ['key' => 'couriers',        'label' => 'Koeriersdiens',       'sort_order' => 50, 'is_system' => false],
            ['key' => 'airtime',         'label' => 'Airtime/Data',        'sort_order' => 60, 'is_system' => false],
            ['key' => 'petrol',          'label' => 'Petrol',              'sort_order' => 70, 'is_system' => false],
            ['key' => 'admin_fee',       'label' => 'Adminfooi',           'sort_order' => 80, 'is_system' => false],
            ['key' => 'accommodation',   'label' => 'Akkommodasie',        'sort_order' => 90, 'is_system' => false],
            ['key' => 'extras',          'label' => "Ekstra's",            'sort_order' => 100, 'is_system' => false],
            ['key' => 'payfast',         'label' => 'PayFast Fooie',       'sort_order' => 200, 'is_system' => true],
            ['key' => 'cape_tennis_fee', 'label' => 'Cape Tennis Fooi',    'sort_order' => 210, 'is_system' => true],
            ['key' => 'other',           'label' => "Ander",               'sort_order' => 999, 'is_system' => false],
        ];

        $now = now();
        foreach ($types as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }

        DB::table('expense_types')->insert($types);
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_types');
    }
};
