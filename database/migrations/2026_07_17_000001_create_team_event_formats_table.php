<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) && Schema::hasTable('events')) {
            DB::statement('ALTER TABLE `events` ENGINE = InnoDB');
        }

        if (! Schema::hasTable('team_event_formats')) {
            Schema::create('team_event_formats', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('event_id')->nullable()->index();
                $table->string('name', 191);
                $table->unsignedTinyInteger('min_roster_size')->default(1);
                $table->unsignedTinyInteger('max_roster_size')->default(12);
                $table->boolean('allow_player_reuse')->default(false);
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('team_event_format_rubbers')) {
            Schema::create('team_event_format_rubbers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('format_id');
                $table->unsignedTinyInteger('sequence');
                $table->string('rubber_code', 30); // singles|reverse_singles|doubles|mixed_doubles
                $table->string('name', 100);
                $table->string('gender_rule', 20)->nullable(); // male|female|mixed|null
                $table->unsignedTinyInteger('player_count_per_team')->default(1);
                $table->unsignedTinyInteger('singles_position')->nullable();
                $table->unsignedTinyInteger('reverse_from_position')->nullable();
                $table->boolean('is_required')->default(true);
                $table->timestamps();

                $table->unique(['format_id', 'sequence']);
                $table->foreign('format_id')
                      ->references('id')->on('team_event_formats')
                      ->cascadeOnDelete();
            });
        }

        $this->ensureForeignKey(
            'team_event_formats',
            'team_event_formats_event_id_foreign',
            'event_id',
            'events',
            true
        );
        $this->ensureForeignKey(
            'team_event_format_rubbers',
            'team_event_format_rubbers_format_id_foreign',
            'format_id',
            'team_event_formats',
            false
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('team_event_format_rubbers');
        Schema::dropIfExists('team_event_formats');
    }

    private function ensureForeignKey(
        string $tableName,
        string $name,
        string $column,
        string $parentTable,
        bool $setNull,
    ): void {
        $exists = collect(Schema::getForeignKeys($tableName))
            ->contains(fn (array $foreignKey) => ($foreignKey['name'] ?? null) === $name);

        if ($exists) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($name, $column, $parentTable, $setNull) {
            $foreign = $table->foreign($column, $name)->references('id')->on($parentTable);

            if ($setNull) {
                $foreign->nullOnDelete();
            } else {
                $foreign->cascadeOnDelete();
            }
        });
    }
};
