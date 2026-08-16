<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These are legacy MyISAM tables in older Cape Tennis databases.
        // MySQL cannot create foreign keys to or from MyISAM tables, so make
        // the three tables participating in this migration chain FK-capable
        // before creating team_ties or extending team_fixtures.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            foreach (['draws', 'teams', 'team_fixtures'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::statement("ALTER TABLE `{$table}` ENGINE = InnoDB");
                }
            }
        }

        if (! Schema::hasTable('team_ties')) {
            Schema::create('team_ties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('draw_id');
                $table->unsignedSmallInteger('round_nr');
                $table->unsignedSmallInteger('tie_nr');
                $table->unsignedBigInteger('home_team_id');
                $table->unsignedBigInteger('away_team_id');
                $table->string('status', 20)->default('draft'); // draft|validated|published|completed
                $table->unsignedBigInteger('winner_team_id')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->unique(['draw_id', 'round_nr', 'tie_nr']);
                $table->unique(['draw_id', 'round_nr', 'home_team_id', 'away_team_id']);

                $table->foreign('draw_id')->references('id')->on('draws')->cascadeOnDelete();
                $table->foreign('home_team_id')->references('id')->on('teams')->cascadeOnDelete();
                $table->foreign('away_team_id')->references('id')->on('teams')->cascadeOnDelete();
                $table->foreign('winner_team_id')->references('id')->on('teams')->nullOnDelete();
            });

            return;
        }

        // MySQL can leave the table behind when CREATE TABLE fails while
        // adding a foreign key. Repair that partial state on the next run.
        $this->ensureForeignKey('team_ties_draw_id_foreign', 'draw_id', 'draws', 'cascade');
        $this->ensureForeignKey('team_ties_home_team_id_foreign', 'home_team_id', 'teams', 'cascade');
        $this->ensureForeignKey('team_ties_away_team_id_foreign', 'away_team_id', 'teams', 'cascade');
        $this->ensureForeignKey('team_ties_winner_team_id_foreign', 'winner_team_id', 'teams', 'set null');
    }

    public function down(): void
    {
        Schema::dropIfExists('team_ties');
    }

    private function ensureForeignKey(
        string $name,
        string $column,
        string $parentTable,
        string $onDelete,
    ): void {
        $exists = collect(Schema::getForeignKeys('team_ties'))
            ->contains(fn (array $foreignKey) => ($foreignKey['name'] ?? null) === $name);

        if ($exists) {
            return;
        }

        Schema::table('team_ties', function (Blueprint $table) use ($name, $column, $parentTable, $onDelete) {
            $foreign = $table->foreign($column, $name)->references('id')->on($parentTable);

            if ($onDelete === 'set null') {
                $foreign->nullOnDelete();
            } else {
                $foreign->cascadeOnDelete();
            }
        });
    }
};
