<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['category_event_registrations', 'team_payment_orders'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $column = collect(Schema::getColumns($tableName))->firstWhere('name', 'refund_status');

            if ($column === null || $column['type_name'] !== 'enum') {
                continue;
            }

            // Match the canonical string schema, retaining historical values and
            // column semantics without rewriting any refund or payment records.
            Schema::table($tableName, function (Blueprint $table) use ($column): void {
                $table->string('refund_status')
                    ->nullable($column['nullable'])
                    ->default($column['default'])
                    ->comment($column['comment'] ?? '')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        // Narrowing back to an enum could destroy waived or historical states.
    }
};
