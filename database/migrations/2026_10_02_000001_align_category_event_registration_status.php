<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('category_event_registrations', 'status')) {
            return;
        }

        $statusColumn = collect(Schema::getColumns('category_event_registrations'))
            ->firstWhere('name', 'status');

        // Older installations used an enum that cannot represent pending checkout.
        // Match the canonical schema without rewriting registration or payment state.
        Schema::table('category_event_registrations', function (Blueprint $table) use ($statusColumn) {
            $table->string('status')->nullable($statusColumn['nullable'])->default('active')->change();
        });
    }

    public function down(): void
    {
        // Do not narrow to the legacy enum: existing checkout states would be lost.
    }
};
