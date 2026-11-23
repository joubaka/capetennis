<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, EventNomination, EventType, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, User};
use App\Services\InterprovincialTrials\NominationHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterproNominationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertTrue((DB::getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:')
            || (DB::getDriverName() === 'mysql' && DB::connection()->getDatabaseName() === 'ct_testing'));
    }

    public function test_complete_register_counts_archived_withdrawals_and_removed_history_once(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $active = $this->nominee($event, $category, $batch, 'Active', 'paid_confirmed');
        $archived = $this->nominee($event, $category, $batch, 'Archived', 'withdrawn');
        $archived->forceFill(['archived_at' => now(), 'archive_reason' => 'Withdrawn'])->save();
        $this->nominee($event, $category, $batch, 'Withdrawn', 'withdrawn');
        $this->nominee($event, $category, $batch, 'Declined', 'admin_declined');
        $this->nominee($event, $category, $batch, 'Cancelled', 'cancelled');
        $this->deleted($event, $category, 90001, 'Removed');
        $this->deleted($event, $category, 90001, 'Removed');
        \Illuminate\Support\Facades\Schema::withoutForeignKeyConstraints(function () use ($event, $category, $batch) {
            DB::table('interprovincial_trial_invitations')->insert(['batch_id' => $batch->id, 'event_id' => $event->id,
                'category_event_id' => $category->id, 'nomination_id' => 90001, 'player_id' => null, 'status' => 'sent']);
        });
        $history = app(NominationHistory::class)->build($event);
        $this->assertSame(6, $history['rows']->total());
        $this->assertSame(2, (int) $history['counts']['withdrawn']);
        $this->assertSame(1, (int) $history['counts']['removed']);
        $this->assertSame(6, (int) $history['categoryCounts'][$category->id]);
        $filtered = $this->actingAs($actor)->get(route('backend.interprovincial-trials.invitations.index', [$event, 'nomination_status' => 'withdrawn']))->assertOk();
        $this->assertSame(2, $filtered->viewData('nominations')->total());
        $filtered->assertSee('Archived nomination')->assertSee('Retained history')->assertDontSee('Active Candidate');
        $groups = $filtered->viewData('nominationFilterGroups');
        $this->assertSame(6, $groups['all']['count']);
        $this->assertSame(2, $groups['withdrawn']['count']);
        $this->assertSame(1, $groups['registered']['count']);
        $this->assertSame(2, $groups['declined-removed']['count']);
        $combined = $this->actingAs($actor)->get(route('backend.interprovincial-trials.invitations.index', [$event, 'nomination_status' => 'declined-removed']))
            ->assertOk()->assertSee('Declined Candidate')->assertSee('Removed Candidate')->assertSee('Declined / removed');
        $this->assertSame(2, $combined->viewData('nominations')->total());
        $all = $this->actingAs($actor)->get(route('backend.interprovincial-trials.invitations.index', $event))->assertOk()->assertSee('Removed Candidate')->assertSee('Reason not recorded');
        $this->assertSame(0, $all->viewData('historicalInvitations')->total());
        if (getenv('CT_NOMINATION_HISTORY_QA_HTML')) {
            file_put_contents(storage_path('framework/testing/interpro-nomination-history-qa.html'), $all->getContent());
            file_put_contents(storage_path('framework/testing/interpro-nomination-history-withdrawn-qa.html'), $filtered->getContent());
        }
        $this->assertSame('paid_confirmed', $active->actionableInvitation->status);
    }

    public function test_history_rejects_foreign_or_malformed_audit_snapshots_and_current_ids(): void
    {
        [$event, $category, , $batch] = $this->scenario();
        $other = Event::factory()->create();
        $foreign = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $current = $this->nominee($event, $category, $batch, 'Still current', 'paid_confirmed');
        $this->deleted($event, $category, $current->id, 'Old deletion');
        $this->deleted($event, $foreign, 90002, 'Foreign category');
        $this->deleted($event, $category, 90003, 'Foreign snapshot', ['event_id' => $other->id]);
        $this->deleted($event, $category, 90004, 'Malformed snapshot', ['category_event_id' => $category->id.'garbage']);
        $this->deleted($event, $category, 90005, 'Wrong identity', ['id' => 90006]);
        $this->assertSame(1, app(NominationHistory::class)->build($event)['rows']->total());
        $this->deleted($event, $category, 90007, 'Bad identity', ['player_id' => ['bad'], 'nominee_name' => ['bad'], 'nominee_surname' => ['bad']]);
        $history = app(NominationHistory::class)->build($event);
        $this->assertSame('Removed nominee #90007', $history['rows']->getCollection()->last()->display_name);
        $this->get(route('backend.interprovincial-trials.invitations.index', $event))->assertRedirect();
        $this->actingAs(User::factory()->create())->get(route('backend.interprovincial-trials.invitations.index', $event))->assertForbidden();
    }

    public function test_global_totals_and_filtering_survive_pagination_and_new_drafts(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        for ($i = 0; $i < 101; $i++) $this->nominee($event, $category, $batch, 'Nominee'.$i, 'sent');
        $withdrawn = $this->nominee($event, $category, $batch, 'Last withdrawn', 'withdrawn');
        $batch = $batch->replicate();
        $batch->save();
        InterprovincialTrialInvitation::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'batch_id' => $batch->id,
            'nomination_id' => $withdrawn->id, 'player_id' => $withdrawn->player_id, 'status' => 'prepared']);
        $response = $this->actingAs($actor)->get(route('backend.interprovincial-trials.invitations.index', [$event, 'nomination_status' => 'withdrawn']))->assertOk()->assertSee('Last withdrawn Candidate');
        $this->assertSame(1, $response->viewData('nominations')->total());
        $this->assertSame(102, $response->viewData('nominationFilterGroups')['all']['count']);
        $this->assertSame(100, app(NominationHistory::class)->build($event)['rows']->count());
        $this->assertSame(102, app(NominationHistory::class)->build($event)['rows']->total());
    }

    public function test_register_folds_categories_and_history_without_losing_filtered_records(): void
    {
        [$event, $category, $actor, $batch] = $this->scenario();
        $second = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $this->nominee($event, $category, $batch, 'First group', 'withdrawn');
        $withdrawn = $this->nominee($event, $second, $batch, 'Second group', 'withdrawn');
        $withdrawn->actionableInvitation->forceFill(['withdrawn_at' => now()])->save();
        $this->actingAs($actor);
        $url = route('backend.interprovincial-trials.invitations.index', $event);
        $response = $this->get($url)->assertOk()->assertSee('First group Candidate')->assertSee('Second group Candidate');
        $document = new \DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(3, $xpath->query('//div[contains(@class,"interpro-tool-grid")]/details')->length);
        $this->assertSame(1, $xpath->query('//details[@data-nomination-category and @open]')->length);
        $this->assertSame(2, $xpath->query('//details[@data-nomination-category]')->length);
        $this->assertSame(1, $xpath->query('//details[contains(@class,"interpro-history") and not(@open)]')->length);
        $response->assertSee('View history')->assertSee('Withdrawn:')->assertDontSee('No registration activity yet');
        $filtered = $this->get($url.'?nomination_status=withdrawn')->assertOk();
        @$document->loadHTML($filtered->getContent());
        $this->assertSame(2, (new \DOMXPath($document))->query('//details[@data-nomination-category and @open]')->length);
        $this->assertSame(2, $filtered->viewData('nominations')->total());
        if (getenv('CT_NOMINATION_HISTORY_QA_HTML')) {
            file_put_contents(storage_path('framework/testing/interpro-nomination-ordered-qa.html'), $response->getContent());
        }
    }

    private function scenario(): array
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $type = EventType::forceCreate(['name' => 'History trials', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type->id, 'published' => true, 'signUp' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('a',64), 'created_by_user_id' => $actor->id]);
        return [$event, $category, $actor, $batch];
    }

    private function nominee($event, $category, $batch, $name, $status): EventNomination
    {
        $player = Player::factory()->create(['name' => $name, 'surname' => 'Candidate']);
        $nomination = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id]);
        InterprovincialTrialInvitation::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'batch_id' => $batch->id,
            'nomination_id' => $nomination->id, 'player_id' => $player->id, 'status' => $status]);
        return $nomination;
    }

    private function deleted($event, $category, $id, $name, array $overrides = []): void
    {
        DB::table('audit_events')->insert(['event_uuid' => (string) \Illuminate\Support\Str::ulid(), 'occurred_at' => now(), 'created_at' => now(),
            'category' => 'data', 'action' => 'event-nomination.deleted', 'outcome' => 'succeeded', 'source' => 'web',
            'subject_type' => EventNomination::class, 'subject_id' => (string) $id, 'event_id' => $event->id,
            'before' => json_encode(array_replace(['id' => $id, 'event_id' => $event->id, 'category_event_id' => $category->id,
                'player_id' => null, 'nominee_name' => $name, 'nominee_surname' => 'Candidate'], $overrides)), 'integrity_hash' => str_repeat('0',64)]);
    }
}
