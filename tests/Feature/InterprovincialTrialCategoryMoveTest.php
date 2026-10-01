<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Draw, Event, EventNomination, EventType, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, User};
use App\Services\InterprovincialTrials\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterprovincialTrialCategoryMoveTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Interpro Trials', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => true, 'signUp' => true, 'status' => 'open', 'start_date' => now()->addMonth(), 'deadline' => 1]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $source = CategoryEvent::factory()->create(['event_id' => $event->id, 'entry_fee' => 150]);
        $target = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $player = Player::factory()->create();
        $payer = User::factory()->create();
        $nomination = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $source->id, 'player_id' => $player->id]);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $admin->id]);
        $invitation = InterprovincialTrialInvitation::create(['event_id' => $event->id, 'category_event_id' => $source->id, 'player_id' => $player->id, 'nomination_id' => $nomination->id, 'batch_id' => $batch->id, 'status' => 'sent']);
        $order = app(InvitationService::class)->accept($invitation, $payer);
        $entry = $source->categoryEventRegistrations()->sole();
        return compact('admin', 'event', 'source', 'target', 'nomination', 'invitation', 'order', 'entry');
    }

    public function test_move_preserves_payer_amount_and_exact_registration_tuple(): void
    {
        $f = $this->fixture();
        $before = $f['order']->fresh()->getRawOriginal();
        $moved = app(InvitationService::class)->transferEntry($f['entry'], $f['target'], $f['admin']);
        $this->assertSame($f['target']->id, $moved->category_event_id);
        $this->assertSame($f['target']->id, $f['nomination']->fresh()->category_event_id);
        $this->assertSame($f['target']->id, $f['invitation']->fresh()->category_event_id);
        $this->assertSame($f['target']->id, $f['order']->items()->sole()->category_event_id);
        $this->assertSame($before, $f['order']->fresh()->getRawOriginal());
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        app(InvitationService::class)->accept($f['invitation']->fresh(), $f['order']->user);
    }

    public function test_source_draw_blocks_move_without_partial_changes(): void
    {
        $f = $this->fixture();
        Draw::factory()->create(['event_id' => $f['event']->id, 'category_event_id' => $f['source']->id]);
        try {
            app(InvitationService::class)->transferEntry($f['entry'], $f['target'], $f['admin']);
            $this->fail('Draw creation must block category moves.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('category', $exception->errors());
        }
        $this->assertSame($f['source']->id, $f['entry']->fresh()->category_event_id);
        $this->assertSame($f['source']->id, $f['nomination']->fresh()->category_event_id);
        $this->assertSame($f['source']->id, $f['order']->items()->sole()->category_event_id);
    }

    public function test_outsider_cannot_move_entry(): void
    {
        $f = $this->fixture();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(InvitationService::class)->transferEntry($f['entry'], $f['target'], User::factory()->create()->assignRole('admin'));
    }
}
