<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\EventType;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterprovincialTrialBackendParityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Event $event;

    private CategoryEvent $category;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $typeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Interpro Trials',
            'type' => EventType::INDIVIDUAL,
            'code' => EventType::INTERPROVINCIAL_TRIALS_CODE,
        ]);
        $this->event = Event::factory()->create([
            'eventType' => $typeId,
            'published' => true,
            'signUp' => true,
            'status' => 'open',
        ]);
        $this->category = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
    }

    public function test_shared_workspace_navigation_links_and_marks_the_invitation_page_active(): void
    {
        $url = route('backend.interprovincial-trials.invitations.index', $this->event);

        $this->actingAs($this->admin)->get(route('admin.events.overview', $this->event))
            ->assertOk()
            ->assertSee('Nominations &amp; invitations', false)
            ->assertSee($url, false);

        $this->actingAs($this->admin)->get($url)
            ->assertOk()
            ->assertSee('Tournament workspace')
            ->assertSee('Event overview')
            ->assertSee('Nominations &amp; invitations', false)
            ->assertSee('href="'.$url.'"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_unassigned_admin_is_denied_and_does_not_receive_the_interprovincial_tool_link(): void
    {
        $unassigned = User::factory()->create()->assignRole('admin');

        $this->actingAs($unassigned)
            ->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertForbidden();

        $ordinaryTypeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Ordinary singles',
            'type' => EventType::INDIVIDUAL,
            'code' => 'ordinary-singles-parity',
        ]);
        $ordinaryEvent = Event::factory()->create(['eventType' => $ordinaryTypeId]);
        DB::table('event_admins')->insert(['event_id' => $ordinaryEvent->id, 'user_id' => $this->admin->id]);

        $this->actingAs($this->admin)->get(route('admin.events.overview', $ordinaryEvent))
            ->assertOk()
            ->assertDontSee('Nominations &amp; invitations', false);
    }

    public function test_readiness_is_observational_bounded_and_event_scoped(): void
    {
        $financialTables = ['registrations', 'registration_orders', 'registration_order_items', 'wallet_transactions'];
        $before = collect($financialTables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

        $this->actingAs($this->admin)
            ->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()
            ->assertSee('Invitation readiness')
            ->assertSee('Add nominations')
            ->assertSee('Recipients need attention')
            ->assertSee('Message not saved')
            ->assertSee('Review required')
            ->assertSee('Registration open');

        $player = Player::factory()->create();
        $nomination = EventNomination::create([
            'event_id' => $this->event->id,
            'category_event_id' => $this->category->id,
            'player_id' => $player->id,
        ]);
        $hash = str_repeat('a', 64);
        $batch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $this->event->id,
            'status' => InterprovincialTrialInvitationBatch::REVIEWED,
            'snapshot_hash' => str_repeat('b', 64),
            'created_by_user_id' => $this->admin->id,
            'reviewed_by_user_id' => $this->admin->id,
            'reviewed_at' => now(),
            'email_subject' => 'Trials invitation',
            'email_body' => 'Please review your invitation.',
            'message_hash' => $hash,
            'reviewed_message_hash' => $hash,
        ]);
        InterprovincialTrialInvitation::create([
            'batch_id' => $batch->id,
            'event_id' => $this->event->id,
            'category_event_id' => $this->category->id,
            'nomination_id' => $nomination->id,
            'player_id' => $player->id,
            'recipient_email' => 'ready@example.test',
            'status' => 'sent',
        ]);

        $other = Event::factory()->create(['eventType' => $this->event->eventType]);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $otherPlayer = Player::factory()->create();
        $otherNomination = EventNomination::create([
            'event_id' => $other->id,
            'category_event_id' => $otherCategory->id,
            'player_id' => $otherPlayer->id,
        ]);
        $otherBatch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $other->id,
            'status' => InterprovincialTrialInvitationBatch::DRAFT,
            'snapshot_hash' => str_repeat('c', 64),
            'created_by_user_id' => $this->admin->id,
        ]);
        InterprovincialTrialInvitation::create([
            'batch_id' => $otherBatch->id,
            'event_id' => $other->id,
            'category_event_id' => $otherCategory->id,
            'nomination_id' => $otherNomination->id,
            'player_id' => $otherPlayer->id,
            'recipient_email' => null,
            'status' => 'failed',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('backend.interprovincial-trials.invitations.index', $this->event))
            ->assertOk()
            ->assertSee('Nominations ready')
            ->assertSee('Recipients ready')
            ->assertSee('Message saved')
            ->assertSee('Message reviewed')
            ->assertSee('Batch Reviewed')
            ->assertSee('Sent: 1')
            ->assertDontSee('Failed: 1');

        $response->assertSeeInOrder(['Ready recipients', '>1<', 'Recipient blockers', '>0<'], false);
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "The readiness page must not write {$table}.");
        }
    }
}
