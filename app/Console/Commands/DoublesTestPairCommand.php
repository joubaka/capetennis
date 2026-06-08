<?php

namespace App\Console\Commands;

use App\Models\CategoryEvent;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Registration;
use App\Services\FeatureFlags;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * doubles:test-pair
 *
 * PHASE 1 FOUNDATION — admin test tool only.
 *
 * Creates Registrations each containing two Players for display testing.
 * Does NOT create any:
 *   - RegistrationOrders
 *   - Payments
 *
 * Usage:
 *   php artisan doubles:test-pair
 *   php artisan doubles:test-pair --pairs=4
 *   php artisan doubles:test-pair --category-event=5
 */
class DoublesTestPairCommand extends Command
{
    protected $signature = 'doubles:test-pair
                            {--player1= : ID of first player (single pair mode)}
                            {--player2= : ID of second player (single pair mode)}
                            {--pairs=1  : Number of test pairs to create}
                            {--category-event= : CategoryEvent ID to create CERs for (optional)}';

    protected $description = '[DOUBLES FOUNDATION] Create test Registrations with two players each for display/draw auditing.';

    public function handle(): int
    {
        if (! FeatureFlags::enabled(FeatureFlags::DOUBLES_FOUNDATION)) {
            $this->error('DOUBLES_FOUNDATION feature flag is disabled.');
            $this->line('Enable it with: FLAG_DOUBLES_FOUNDATION=true in .env (local/staging only)');
            return self::FAILURE;
        }

        $pairsCount = max(1, (int) $this->option('pairs'));
        $ceId       = $this->option('category-event');
        $ce         = $ceId ? CategoryEvent::find($ceId) : null;

        if ($ceId && ! $ce) {
            $this->error("CategoryEvent ID {$ceId} not found.");
            return self::FAILURE;
        }

        // Single-pair explicit player IDs
        if ($pairsCount === 1 && $this->option('player1') && $this->option('player2')) {
            $players = collect([
                Player::find($this->option('player1')),
                Player::find($this->option('player2')),
            ]);
            if ($players->contains(null)) {
                $this->error('One or both player IDs not found.');
                return self::FAILURE;
            }
            $playerPool = $players;
        } else {
            $needed = $pairsCount * 2;
            $playerPool = Player::orderBy('id')->limit($needed)->get();
            if ($playerPool->count() < $needed) {
                $this->error("Not enough players in the database. Need {$needed}, found {$playerPool->count()}.");
                return self::FAILURE;
            }
        }

        $results = [];

        DB::transaction(function () use ($pairsCount, $playerPool, $ce, &$results) {
            for ($i = 0; $i < $pairsCount; $i++) {
                $p1 = $playerPool[$i * 2];
                $p2 = $playerPool[$i * 2 + 1];

                $reg = Registration::create([]);

                PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $p1->id]);
                PlayerRegistration::create(['registration_id' => $reg->id, 'player_id' => $p2->id]);

                $cerStatus = '—';
                if ($ce) {
                    $cer = \App\Models\CategoryEventRegistration::create([
                        'category_event_id' => $ce->id,
                        'registration_id'   => $reg->id,
                        'user_id'           => $p1->user_id ?? null,
                        'payment_status_id' => 1, // paid — so draw picks it up
                        'status'            => 'active',
                    ]);
                    $cerStatus = "CER #{$cer->id} (paid)";
                }

                $results[] = [
                    'pair'            => $i + 1,
                    'registration_id' => $reg->id,
                    'player1'         => "{$p1->name} {$p1->surname} (#{$p1->id})",
                    'player2'         => "{$p2->name} {$p2->surname} (#{$p2->id})",
                    'displayName'     => $reg->displayName(),
                    'displayShort'    => $reg->displayShortName(),
                    'cer'             => $cerStatus,
                ];
            }
        });

        $this->info("✅ {$pairsCount} test pair(s) created successfully.");
        $this->table(
            ['Pair', 'Reg ID', 'Player 1', 'Player 2', 'displayName()', 'displayShortName()', 'CER'],
            collect($results)->map(fn($r) => [
                $r['pair'], $r['registration_id'], $r['player1'], $r['player2'],
                $r['displayName'], $r['displayShort'], $r['cer'],
            ])
        );

        $this->newLine();
        if (! $ce) {
            $this->warn('REMINDER: These registrations have no CER. Use --category-event=ID to attach them to a draw.');
        } else {
            $this->info("Registrations attached to CategoryEvent #{$ce->id} as paid CERs — ready for draw generation.");
        }

        return self::SUCCESS;
    }
}
