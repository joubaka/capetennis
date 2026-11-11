<?php

namespace Tests\Feature;

use App\Models\{BulkEmailLog, Category, CategoryEvent, Event, EventAdmin, EventType, Player, User};
use App\Services\CavaliersRosterReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CavaliersRosterReportTest extends TestCase
{
    use RefreshDatabase;

    private function setupReport(): array
    {
        Mail::fake();
        Bus::fake();
        $event = Event::factory()->create(['id' => 252, 'eventType' => 5, 'name' => 'Fictional Trials']);
        $type = EventType::forceCreate(['id' => 242, 'name' => 'Team source', 'type' => EventType::TEAM]);
        Event::factory()->create(['id' => 242, 'published' => true, 'eventType' => $type->id, 'name' => 'Inter-Districts Tournament Primary Schools 2026']);
        $actor = User::factory()->create(['name' => 'Fictional Administrator']);
        $actor->assignRole(Role::findOrCreate('admin', 'web'));
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $actor->id]);
        $this->actingAs($actor);
        $players = Player::factory()->count(7)->create();
        foreach (['U11 Girls', 'U12 Boys'] as $name) {
            $division = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $name])->id]);
            DB::table('cavaliers_trial_roster_drafts')->insert(['event_id' => $event->id, 'category_event_id' => $division->id,
                'revision' => (string) Str::uuid(), 'selected' => json_encode([$players[0]->id]), 'reserves' => json_encode($players->slice(1, 5)->pluck('id')->all()), 'declined' => json_encode([$players[6]->id])]);
        }
        $recipient = User::factory()->create(['name' => 'Fictional Reviewer', 'email' => 'fictional-reviewer@example.test']);
        return [$event, $actor, $recipient];
    }

    public function test_previews_all_saved_divisions_and_queues_exact_reviewed_attachment_once(): void
    {
        [$event, $actor, $recipient] = $this->setupReport();
        $preview = $this->postJson(route('backend.cavaliers-report.preview', $event), ['recipient_ids' => [$recipient->id]])->assertOk();
        $preview->assertSee('U11 Girls')->assertSee('U12 Boys');
        $pdf = $this->get($preview->json('pdf_url'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        Mail::assertNothingSent();
        Bus::assertNothingDispatched();
        $this->postJson($preview->json('send_url'))->assertOk()->assertJsonPath('queued', 1);
        $this->postJson($preview->json('send_url'))->assertOk()->assertJsonPath('queued', 0);
        $log = BulkEmailLog::where('status', 'queued')->sole();
        $this->assertSame($pdf->getContent(), base64_decode($log->payload['pdf'], true));
        $message = app(CavaliersRosterReport::class)->authorizedMailable($log);
        $this->assertNotNull($message, 'Signature must survive MySQL JSON key reordering.');
        $this->assertSame($preview->json('html'), $message->render());
        $message->assertHasAttachedData($pdf->getContent(), 'nomination-review.pdf', ['mime' => 'application/pdf']);
        $log->update(['status' => 'sending']);
        $mime = (new \Symfony\Component\Mime\Email)->from(new \Symfony\Component\Mime\Address(config('mail.from.address'), 'Cape Tennis'))
            ->replyTo(config('mail.from.address'))->to($log->recipient_email)->subject($log->payload['subject'])->html($log->payload['body'])
            ->attach(base64_decode($log->payload['pdf'], true), 'nomination-review.pdf', 'application/pdf');
        $eventData = ['cavaliers_report_log_id' => $log->id];
        foreach (app('events')->getRawListeners()[\Illuminate\Mail\Events\MessageSending::class] ?? [] as $registered) {
            $class = is_array($registered) ? $registered[0] : $registered;
            if ($class === \App\Listeners\RequireEventEmailReview::class) break;
            if ($class === \Fedeisas\LaravelMailCssInliner\CssInlinerPlugin::class) app($class)->handle(new \Illuminate\Mail\Events\MessageSending($mime, []));
        }
        $listener = app(\App\Listeners\RequireEventEmailReview::class);
        $this->assertNull($listener->handle(new \Illuminate\Mail\Events\MessageSending($mime, $eventData)));
        try {
            $listener->handle(new \Illuminate\Mail\Events\MessageSending((clone $mime)->cc('unexpected@example.test'), $eventData));
            $this->fail('Unexpected copies must be rejected.');
        } catch (\App\Exceptions\SelectionPreviewMailAuthorizationException) { $this->assertTrue(true); }
        $changed = (new \Symfony\Component\Mime\Email)->from(new \Symfony\Component\Mime\Address(config('mail.from.address'), 'Cape Tennis'))
            ->replyTo(config('mail.from.address'))->to($log->recipient_email)->subject($log->payload['subject'])->html($mime->getHtmlBody())
            ->attach('%PDF-tampered', 'nomination-review.pdf', 'application/pdf');
        try {
            $listener->handle(new \Illuminate\Mail\Events\MessageSending($changed, $eventData));
            $this->fail('Changed attachment must be rejected.');
        } catch (\App\Exceptions\SelectionPreviewMailAuthorizationException) { $this->assertTrue(true); }
        $report = app(CavaliersRosterReport::class)->report($event, $actor);
        $candidates = [];
        foreach ([['Lost All Candidate', true, false, 0, 3], ['Winless Opponent Candidate', false, true, 1, 2], ['Manual Omission Candidate', false, false, null, null]] as [$name, $lost, $only, $wins, $losses]) {
            $candidate = ['id' => 900 + count($report['sections'][0]['rows']), 'name' => $name, 'source' => 'Inter-Districts', 'wins' => $wins, 'losses' => $losses, 'lost_all' => $lost, 'only_beat_winless' => $only, 'starting_credit' => 1];
            $candidate['id'] = 900 + count($candidates);
            $candidates[] = $candidate;
        }
        $report['sections'][0]['explanations'] = app(CavaliersRosterReport::class)->explanationGroups($candidates, []);
        $reportHtml = view('backend.nominations.cavaliers-report-pdf', compact('report'))->render();
        $this->assertStringContainsString('Lost all matches', $reportHtml);
        $this->assertStringContainsString('Only beat winless players', $reportHtml);
        $this->assertStringContainsString('Selector reason required', $reportHtml);
        $this->assertStringContainsString('No counted singles evidence.', $reportHtml);
        if ($output = getenv('CT_ROSTER_SHARE_QA_OUTPUT')) {
            if (! is_dir($output)) mkdir($output, 0777, true);
            foreach ($report['sections'] as &$section) {
                for ($position = 2; $position <= 32; $position++) $section['rows'][] = ['id' => 1000 + $position, 'name' => 'Fictional Selected Player '.str_pad((string) $position, 2, '0', STR_PAD_LEFT), 'status' => 'selected', 'position' => $position, 'evidence' => ['source' => 'Inter-Districts', 'region' => 'Fictional Region', 'wins' => 2, 'losses' => 1, 'lost_all' => false, 'only_beat_winless' => false]];
                $section['rows'] = collect($section['rows'])->sortBy(fn ($row) => array_search($row['status'], ['selected', 'reserves', 'declined', 'omitted'], true) * 1000 + ($row['position'] ?? 999))->values()->all();
            }
            unset($section);
            $reportHtml = view('backend.nominations.cavaliers-report-pdf', compact('report'))->render();
            file_put_contents($output.'/report.pdf', app(CavaliersRosterReport::class)->pdf($report));
            file_put_contents($output.'/report.html', $reportHtml);
            file_put_contents($output.'/share.html', $this->get(route('backend.cavaliers-report.index', $event))->getContent());
            file_put_contents($output.'/preview.json', json_encode($preview->json(), JSON_THROW_ON_ERROR));
        }
        $payload = $log->payload; $payload['body'] .= 'tampered'; $log->payload = $payload;
        $this->assertNull(app(CavaliersRosterReport::class)->authorizedMailable($log));
    }

    public function test_rejects_stale_roster_recipient_expiry_and_other_actors(): void
    {
        [$event, $actor, $recipient] = $this->setupReport();
        $url = route('backend.cavaliers-report.preview', $event);
        $preview = $this->postJson($url, ['recipient_ids' => [$recipient->id]])->assertOk();
        $recipient->update(['email' => 'changed@example.test']);
        $this->postJson($preview->json('send_url'))->assertConflict();
        $preview = $this->postJson($url, ['recipient_ids' => [$recipient->id]])->assertOk();
        DB::table('cavaliers_trial_roster_drafts')->where('event_id', $event->id)->update(['revision' => (string) Str::uuid()]);
        $this->postJson($preview->json('send_url'))->assertConflict();
        $preview = $this->postJson($url, ['recipient_ids' => [$recipient->id]])->assertOk();
        $this->travel(31)->minutes();
        $this->postJson($preview->json('send_url'))->assertConflict();
        $this->travelBack();
        $this->actingAs(User::factory()->create())->getJson(route('backend.cavaliers-report.index', $event))->assertForbidden();
        $this->postJson($url, ['recipient_ids' => [$recipient->id]])->assertForbidden();
        Mail::assertNothingSent();
        Bus::assertNothingDispatched();
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_validates_recipients_and_event_isolation(): void
    {
        [$event, $actor, $recipient] = $this->setupReport();
        $url = route('backend.cavaliers-report.preview', $event);
        $this->postJson($url, ['recipient_ids' => []])->assertUnprocessable();
        $this->postJson($url, ['recipient_ids' => [$recipient->id, $recipient->id]])->assertUnprocessable();
        $this->postJson($url, ['recipient_ids' => [999999]])->assertUnprocessable();
        $other = User::factory()->create(['email' => 'invalid-address']);
        $this->postJson($url, ['recipient_ids' => [$recipient->id, $other->id]])->assertUnprocessable();
        $foreign = Event::factory()->create(['eventType' => 5]);
        $this->getJson(route('backend.cavaliers-report.index', $foreign))->assertNotFound();
        $this->getJson(route('backend.cavaliers-report.index', $event).'?q=Fictional')->assertOk()->assertJsonStructure(['users']);
    }
}
