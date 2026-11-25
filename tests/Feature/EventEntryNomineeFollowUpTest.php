<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, Registration, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventEntryNomineeFollowUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_rows_include_only_current_unanswered_nominees_and_preserve_confirmed_counts(): void
    {
        $actor = $this->actor('super-user');
        $type = \App\Models\EventType::forceCreate(['name' => 'Synthetic follow-up trials', 'type' => \App\Models\EventType::INDIVIDUAL, 'code' => \App\Models\EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type->id]);
        $category = CategoryEvent::factory()->for($event)->create(['nominations_published' => true]);
        $waiting = $this->nominee($category, 'Waiting');
        $this->invitation($waiting, $actor, 'sent');
        $this->invitation($waiting, $actor, 'declined', Event::factory()->create()->id);
        $profileless = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'nominee_name' => 'Profileless', 'nominee_surname' => 'Candidate', 'nominee_email' => 'profileless@example.test']);
        foreach (['declined', 'admin_declined', 'withdrawn', 'accepted_pending_payment', 'paid_confirmed'] as $status) {
            $responded = $this->nominee($category, $status);
            $this->invitation($responded, $actor, $status);
            $this->invitation($responded, $actor, 'prepared');
        }
        $archived = $this->nominee($category, 'Archived');
        $archived->forceFill(['archived_at' => now()])->save();
        $this->nominee(CategoryEvent::factory()->for($event)->create(['nominations_published' => false]), 'Unpublished');
        $this->nominee(CategoryEvent::factory()->create(['nominations_published' => true]), 'OtherEvent');
        foreach (['Paid', 'Pending', 'Withdrawn', 'DeletedWithdrawn', 'Abandoned'] as $name) {
            $nomination = $this->nominee($category, $name);
            $registration = Registration::factory()->create();
            DB::table('player_registrations')->insert(['player_id' => $nomination->player_id, 'registration_id' => $registration->id]);
            $withdrawn = str_contains($name, 'Withdrawn');
            $entry = CategoryEventRegistration::factory()->create(['category_event_id' => $category->id,
                'registration_id' => $registration->id, 'payment_status_id' => $name === 'Paid' ? 1 : 0,
                'status' => $withdrawn ? 'withdrawn' : 'active', 'withdrawn_at' => $withdrawn ? now() : null]);
            if (in_array($name, ['DeletedWithdrawn', 'Abandoned'])) $entry->delete();
        }

        $response = $this->actingAs($actor)->get(route('admin.events.entries.new', $event))->assertOk();
        $visible = $response->viewData('unansweredNominations')->flatten()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$waiting->id, $profileless->id, EventNomination::whereHas('player', fn ($q) => $q->where('name', 'Abandoned'))->value('id')], $visible);
        $response->assertSee('No response')->assertSee('tel:0123456789', false)
            ->assertSee('profileless@example.test')->assertSee('1</span> confirmed entries', false)
            ->assertSeeInOrder(['Paid Candidate', 'Waiting Candidate']);
    }

    public function test_contacts_require_assigned_admin_and_are_denied_to_unrelated_users(): void
    {
        $type = \App\Models\EventType::forceCreate(['name' => 'Synthetic follow-up trials', 'type' => \App\Models\EventType::INDIVIDUAL, 'code' => \App\Models\EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type->id]);
        $category = CategoryEvent::factory()->for($event)->create(['nominations_published' => true]);
        $this->nominee($category, 'PrivateContact');
        $admin = $this->actor('admin');
        $this->actingAs($admin)->get(route('admin.events.entries.new', $event))->assertForbidden();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->get(route('admin.events.entries.new', $event))->assertOk()->assertSee('PrivateContact Candidate');
        foreach (['convenor', 'score-keeper'] as $role) {
            $convenor = $this->actor($role);
            \App\Models\EventConvenor::create(['user_id' => $convenor->id, 'event_id' => $event->id, 'role' => $role]);
            $this->actingAs($convenor)->get(route('admin.events.entries.new', $event))->assertOk()
                ->assertDontSee('PrivateContact Candidate')->assertDontSee('tel:0123456789', false)
                ->assertDontSee('data-nomination-id=', false);
        }
        $this->actingAs(User::factory()->create())->get(route('admin.events.entries.new', $event))->assertForbidden();
    }

    private function actor(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        return User::factory()->create()->assignRole($role);
    }

    private function nominee(CategoryEvent $category, string $name): EventNomination
    {
        $player = Player::factory()->create(['name' => $name, 'surname' => 'Candidate', 'cellNr' => '0123456789']);
        return EventNomination::create(['event_id' => $category->event_id, 'category_event_id' => $category->id, 'player_id' => $player->id]);
    }

    private function invitation(EventNomination $nomination, User $actor, string $status, ?int $eventId = null): void
    {
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $eventId ?? $nomination->event_id,
            'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $actor->id]);
        InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $eventId ?? $nomination->event_id,
            'category_event_id' => $nomination->category_event_id, 'nomination_id' => $nomination->id,
            'player_id' => $nomination->player_id, 'status' => $status]);
    }
}
