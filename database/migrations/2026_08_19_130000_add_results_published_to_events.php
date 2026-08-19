<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'results_published')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->boolean('results_published')->default(false)->after('published');
            });
        }
    }

    public function down(): void
    {
        // Intentionally retained: this flag predates the migration in production
        // and is also used by the public event-results page.
    }
};
