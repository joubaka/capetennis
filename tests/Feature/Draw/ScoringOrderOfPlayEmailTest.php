<?php

namespace Tests\Feature\Draw;

use App\Jobs\SendBulkEmailJob;
use App\Models\{BulkEmailLog, Draw, Event, EventConvenor, Fixture, OrderOfPlay, Player, Registration, TeamFixture, User, Venue};
use App\Services\Scoring\OrderOfPlayDelivery;
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScoringOrderOfPlayEmailTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private Draw $draw;
    private Venue $venue;
    private User $actor;
    private Fixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([SendBulkEmailJob::class]);
        foreach (['admin', 'score-keeper'] as $role) Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $this->event = Event::factory()->create();
        $this->draw = Draw::factory()->create(['event_id' => $this->event->id, 'drawName' => 'Under 19 Boys', 'published' => false, 'oop_published' => false]);
        $this->venue = Venue::forceCreate(['name' => 'Bloemhof']);
        $this->event->venues()->attach($this->venue, ['num_courts' => 8]);
        $this->actor = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->actor->id]);
        $registrations = collect(['Alex', 'Sam'])->map(function ($name) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create(['name' => $name, 'surname' => 'Example']));
            return $registration->id;
        });
        $this->fixture = Fixture::factory()->create(['draw_id' => $this->draw->id, 'match_nr' => 1,
            'registration1_id' => $registrations[0], 'registration2_id' => $registrations[1]]);
        OrderOfPlay::create(['fixture_id' => $this->fixture->id, 'draw_id' => $this->draw->id,
            'venue_id' => $this->venue->id, 'court' => '1', 'time' => '2026-10-30 13:30:00']);
        $this->actingAs($this->actor);
    }

    private function review(array $scope = []): \Illuminate\Testing\TestResponse
    {
        return $this->get(route('frontend.scoring.email', ['event' => $this->event, 'venue' => $this->venue,
            'source' => 'working', 'date' => '2026-10-30', ...$scope]));
    }

    private function input(\Illuminate\Testing\TestResponse $review): array
    {
        return [...$review->viewData('scope'), 'recipient' => 'convenor@example.test',
            'request_key' => $review->viewData('requestKey'), 'review_token' => $review->viewData('reviewToken')];
    }

    private function send(array $input): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('frontend.scoring.email.send', [$this->event, $this->venue]), $input);
    }

    public function test_review_is_read_only_and_pdf_contains_only_the_current_venue_day_and_draw_scope(): void
    {
        $otherDraw = Draw::factory()->create(['event_id' => $this->event->id, 'drawName' => 'Excluded Girls']);
        $otherFixture = Fixture::factory()->create(['draw_id' => $otherDraw->id]);
        OrderOfPlay::create(['fixture_id' => $otherFixture->id, 'draw_id' => $otherDraw->id,
            'venue_id' => $this->venue->id, 'court' => '2', 'time' => '2026-10-30 15:00:00']);
        $nextDay = Fixture::factory()->create(['draw_id' => $this->draw->id]);
        OrderOfPlay::create(['fixture_id' => $nextDay->id, 'draw_id' => $this->draw->id,
            'venue_id' => $this->venue->id, 'court' => '3', 'time' => '2026-10-31 13:30:00']);
        $review = $this->review(['draw' => $this->draw->id])->assertOk()->assertSee('Recipient email')
            ->assertSee('Working schedule')->assertSee('1 match')->assertDontSee('Excluded Girls');
        $this->assertStringContainsString('no-store', $review->headers->get('Cache-Control'));
        $rows = $review->viewData('rows');
        $this->assertCount(1, $rows);
        $this->assertSame(['Alex Example', 'Sam Example'], $rows[0]['players']);
        $this->assertSame('2026-10-30 13:30:00', $rows[0]['time']);
        $pdfHtml = view('frontend.scoring.order-of-play-pdf', ['event' => $this->event, 'venue' => $this->venue,
            'scope' => $review->viewData('scope'), 'rows' => $rows])->render();
        $this->assertStringContainsString('Alex Example / Sam Example', $pdfHtml);
        $this->assertStringNotContainsString('Excluded Girls', $pdfHtml);
        $preview = $this->get(route('frontend.scoring.email.preview', ['event' => $this->event, 'venue' => $this->venue,
            ...$review->viewData('scope'), 'review_token' => $review->viewData('reviewToken')]))->assertOk();
        $this->assertStringStartsWith('%PDF-', $preview->getContent());
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNothingDispatched();
        $this->assertFalse((bool) $this->draw->fresh()->published);
        $this->assertFalse((bool) $this->draw->fresh()->oop_published);
    }

    public function test_typed_recipient_is_validated_and_send_is_deduplicated_with_an_immutable_signed_pdf(): void
    {
        $review = $this->review()->assertOk();
        $input = $this->input($review);
        foreach (['invalid', 'first@example.test,second@example.test', str_repeat('a', 255).'@example.test'] as $recipient) {
            $this->send([...$input, 'recipient' => $recipient])->assertUnprocessable();
        }
        $this->send([...$input, 'recipient' => 'CONVENOR@example.test', 'subject' => 'ignored', 'pdf' => 'ignored'])->assertOk();
        $this->send($input)->assertOk();
        $this->assertDatabaseCount('bulk_email_logs', 1);
        Bus::assertDispatchedTimes(SendBulkEmailJob::class, 1);
        $this->send([...$input, 'recipient' => 'other@example.test'])->assertConflict();
        $log = BulkEmailLog::firstOrFail();
        $this->assertSame('convenor@example.test', $log->recipient_email);
        $this->assertStringStartsWith('Order of play:', $log->payload['subject']);
        $this->assertStringStartsWith('%PDF-', base64_decode($log->payload['pdf']));
        $delivery = app(OrderOfPlayDelivery::class);
        $this->assertNotNull($delivery->authorizedMailable($log));
        foreach (['pdf', 'subject', 'venue_id'] as $field) {
            $payload = $log->payload;
            $payload[$field] = $field === 'venue_id' ? 999999 : 'tampered';
            $changed = clone $log;
            $changed->payload = $payload;
            $this->assertNull($delivery->authorizedMailable($changed));
        }
        DB::table('event_admins')->where('user_id', $this->actor->id)->delete();
        $this->assertNull($delivery->authorizedMailable($log));
    }

    public function test_stale_or_forged_reviews_and_foreign_draw_scope_are_rejected(): void
    {
        $input = $this->input($this->review()->assertOk());
        $this->send([...$input, 'review_token' => str_repeat('0', 64)])->assertConflict();
        $this->fixture->orderOfPlay()->update(['time' => '2026-10-30 14:00:00']);
        $this->send($input)->assertConflict();
        $foreign = Draw::factory()->create();
        $this->review(['draw_ids' => [$foreign->id]])->assertNotFound();
        $this->send([...$input, 'draw_ids' => [$foreign->id]])->assertNotFound();
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNothingDispatched();
    }

    public function test_published_copy_retains_public_times_and_excludes_unsnapshotted_working_matches(): void
    {
        app(SchedulePublicationService::class)->publish($this->event, ['draw_id' => $this->draw->id]);
        $this->fixture->orderOfPlay()->update(['time' => '2026-10-30 16:30:00']);
        $new = Fixture::factory()->create(['draw_id' => $this->draw->id]);
        OrderOfPlay::create(['fixture_id' => $new->id, 'draw_id' => $this->draw->id,
            'venue_id' => $this->venue->id, 'court' => '2', 'time' => '2026-10-30 15:00:00']);
        $published = $this->review(['source' => 'published'])->assertOk()->assertSee('Published schedule');
        $this->assertCount(1, $published->viewData('rows'));
        $this->assertSame('2026-10-30 13:30:00', $published->viewData('rows')[0]['time']);
        $working = $this->review()->assertOk();
        $this->assertCount(2, $working->viewData('rows'));
        $this->send($this->input($published))->assertOk();
        $this->assertSame('published', BulkEmailLog::firstOrFail()->payload['scope']['source']);
    }

    public function test_event_and_venue_authorization_are_rechecked_before_transport(): void
    {
        $input = $this->input($this->review()->assertOk());
        $this->actingAs(User::factory()->create())->review()->assertForbidden();
        $this->send($input)->assertForbidden();
        $scorer = User::factory()->create()->assignRole('score-keeper');
        EventConvenor::create(['event_id' => $this->event->id, 'user_id' => $scorer->id, 'role' => 'score-keeper', 'venue_id' => $this->venue->id]);
        $this->actingAs($scorer);
        $review = $this->review()->assertOk();
        $this->send($this->input($review))->assertOk();
        $otherVenue = Venue::forceCreate(['name' => 'Other courts']);
        $this->event->venues()->attach($otherVenue, ['num_courts' => 2]);
        $this->get(route('frontend.scoring.email', ['event' => $this->event, 'venue' => $otherVenue, 'source' => 'working']))->assertForbidden();
        $foreign = Event::factory()->create();
        $this->get(route('frontend.scoring.email', ['event' => $foreign, 'venue' => $this->venue, 'source' => 'working']))->assertForbidden();
        EventConvenor::where('user_id', $scorer->id)->update(['venue_id' => $otherVenue->id]);
        $this->assertNull(app(OrderOfPlayDelivery::class)->authorizedMailable(BulkEmailLog::firstOrFail()));
    }

    public function test_team_fixture_copy_and_managed_mail_guard_accept_only_the_exact_recipient_and_attachment(): void
    {
        TeamFixture::create(['draw_id' => $this->draw->id, 'round_nr' => 1, 'tie_nr' => 1, 'fixture_type' => 1, 'match_nr' => 8,
            'scheduled_at' => '2026-10-30 15:00:00', 'venue_id' => $this->venue->id, 'court_label' => '2']);
        $review = $this->review()->assertOk();
        $this->assertCount(2, $review->viewData('rows'));
        $this->assertStringStartsWith('team:', $review->viewData('rows')[1]['key']);
        $this->send($this->input($review))->assertOk();
        $log = BulkEmailLog::firstOrFail();
        $log->update(['status' => 'sending']);
        Mail::mailer('array')->to($log->recipient_email)->send(app(OrderOfPlayDelivery::class)->authorizedMailable($log));
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages[0]->getOriginalMessage();
        $this->assertCount(1, $message->getAttachments());
        foreach (['recipient', 'attachment'] as $change) {
            $tampered = clone $message;
            if ($change === 'recipient') $tampered->addTo('unapproved@example.test');
            else $tampered->attach('tampered', 'order-of-play.pdf', 'application/pdf');
            try {
                app(\App\Listeners\RequireEventEmailReview::class)->handle(new \Illuminate\Mail\Events\MessageSending($tampered, ['scoring_order_of_play_log_id' => $log->id]));
                $this->fail('Changed recipient or PDF must be rejected.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
        }
    }
}
