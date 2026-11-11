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

    private function composition(): array
    {
        return ['subject' => 'Fictional team review — U11 Girls / U12 Boys',
            'message' => "Dear reviewers,\n\nPlease review the attached teams.\n<script>alert('example')</script>\nKind regards,\nCape Tennis",
            'from_name' => 'Fictional Selection Panel', 'from_address' => 'verified-sender@example.test', 'reply_to' => 'selection-replies@example.test'];
    }

    private function previewInput(array $ids): array
    {
        return ['recipient_ids' => $ids, ...$this->composition()];
    }

    private function setupReport(): array
    {
        Mail::fake();
        Bus::fake();
        config(['mail.from.address' => 'default-sender@example.test', 'mail.from.name' => 'Cape Tennis', 'mail.allowed_from_addresses' => ['verified-sender@example.test']]);
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
        $preview = $this->postJson(route('backend.cavaliers-report.preview', $event), $this->previewInput([$recipient->id]))->assertOk();
        $preview->assertJsonPath('subject', $this->composition()['subject'])->assertJsonPath('message', $this->composition()['message']);
        $this->assertStringContainsString('&lt;script&gt;', $preview->json('html'));
        $this->assertStringNotContainsString('<script>', $preview->json('html'));
        $this->assertStringContainsString("Dear reviewers,<br />\n<br />", $preview->json('html'));
        $pdf = $this->get($preview->json('pdf_url'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        Mail::assertNothingSent();
        Bus::assertNothingDispatched();
        $this->postJson($preview->json('send_url'), $this->composition())->assertOk()->assertJsonPath('queued', 1);
        $this->postJson($preview->json('send_url'), $this->composition())->assertOk()->assertJsonPath('queued', 0);
        $log = BulkEmailLog::where('status', 'queued')->sole();
        $this->assertSame($pdf->getContent(), base64_decode($log->payload['pdf'], true));
        $this->assertSame($this->composition()['subject'], $log->payload['subject']);
        $this->assertSame($this->composition()['message'], $log->payload['message']);
        foreach (['from_name', 'from_address', 'reply_to'] as $field) $this->assertSame($this->composition()[$field], $log->payload[$field]);
        $message = app(CavaliersRosterReport::class)->authorizedMailable($log);
        $this->assertNotNull($message, 'Signature must survive MySQL JSON key reordering.');
        $this->assertSame($preview->json('html'), $message->render());
        $message->assertHasAttachedData($pdf->getContent(), 'nomination-review.pdf', ['mime' => 'application/pdf']);
        $log->update(['status' => 'sending']);
        $mime = (new \Symfony\Component\Mime\Email)->from(new \Symfony\Component\Mime\Address($message->fromAddress, $message->fromName))
            ->replyTo($message->replyToAddress)->to($log->recipient_email)->subject($log->payload['subject'])->html($log->payload['body'])
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
        foreach ([(clone $mime)->from('changed@example.test'), (clone $mime)->from(new \Symfony\Component\Mime\Address($message->fromAddress, 'Changed sender')), (clone $mime)->replyTo('changed@example.test')] as $changedHeaders) {
            try {
                $listener->handle(new \Illuminate\Mail\Events\MessageSending($changedHeaders, $eventData));
                $this->fail('Unreviewed sender or reply-to must be rejected.');
            } catch (\App\Exceptions\SelectionPreviewMailAuthorizationException) { $this->assertTrue(true); }
        }
        $changed = (new \Symfony\Component\Mime\Email)->from(new \Symfony\Component\Mime\Address($message->fromAddress, $message->fromName))
            ->replyTo($message->replyToAddress)->to($log->recipient_email)->subject($log->payload['subject'])->html($mime->getHtmlBody())
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
        $this->assertSame('default-sender@example.test', config('mail.from.address'));
        config(['mail.allowed_from_addresses' => []]);
        $this->assertNull(app(CavaliersRosterReport::class)->authorizedMailable($log->fresh()), 'A removed sender identity must not be released to transport.');
    }

    public function test_rejects_stale_roster_recipient_expiry_and_other_actors(): void
    {
        [$event, $actor, $recipient] = $this->setupReport();
        $url = route('backend.cavaliers-report.preview', $event);
        $preview = $this->postJson($url, $this->previewInput([$recipient->id]))->assertOk();
        $recipient->update(['email' => 'changed@example.test']);
        $this->postJson($preview->json('send_url'), $this->composition())->assertConflict();
        $preview = $this->postJson($url, $this->previewInput([$recipient->id]))->assertOk();
        DB::table('cavaliers_trial_roster_drafts')->where('event_id', $event->id)->update(['revision' => (string) Str::uuid()]);
        $this->postJson($preview->json('send_url'), $this->composition())->assertConflict();
        $preview = $this->postJson($url, $this->previewInput([$recipient->id]))->assertOk();
        $this->travel(31)->minutes();
        $this->postJson($preview->json('send_url'), $this->composition())->assertConflict();
        $this->travelBack();
        $this->actingAs(User::factory()->create())->getJson(route('backend.cavaliers-report.index', $event))->assertForbidden();
        $this->postJson($url, $this->previewInput([$recipient->id]))->assertForbidden();
        Mail::assertNothingSent();
        Bus::assertNothingDispatched();
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_validates_recipients_and_event_isolation(): void
    {
        [$event, $actor, $recipient] = $this->setupReport();
        $url = route('backend.cavaliers-report.preview', $event);
        $this->postJson($url, $this->previewInput([]))->assertUnprocessable();
        $this->postJson($url, $this->previewInput([$recipient->id, $recipient->id]))->assertUnprocessable();
        $this->postJson($url, $this->previewInput([999999]))->assertUnprocessable();
        $other = User::factory()->create(['email' => 'invalid-address']);
        $this->postJson($url, $this->previewInput([$recipient->id, $other->id]))->assertUnprocessable();
        $foreign = Event::factory()->create(['eventType' => 5]);
        $this->getJson(route('backend.cavaliers-report.index', $foreign))->assertNotFound();
        $this->getJson(route('backend.cavaliers-report.index', $event).'?q=Fictional')->assertOk()->assertJsonStructure(['users']);
    }

    public function test_composition_is_prefilled_validated_and_must_match_the_reviewed_snapshot(): void
    {
        // Exercise each composition rule independently without the route's separate request-rate limit.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        [$event, $actor, $recipient] = $this->setupReport();
        $this->get(route('backend.cavaliers-report.index', $event))->assertOk()->assertSee('Email subject')->assertSee('Email message')
            ->assertSee('Private nomination review: Fictional Trials')->assertSee('Please review the attached nomination report')
            ->assertSee('Sender name')->assertSee('Reply-to email address')->assertSee('default-sender@example.test');
        $url = route('backend.cavaliers-report.preview', $event);
        foreach ([['subject' => ''], ['subject' => str_repeat('s', 201)], ['subject' => "Subject\r\nBcc: someone@example.test"],
            ['subject' => "Subject\tControl"], ['message' => ''], ['message' => str_repeat('m', 10001)], ['message' => "Message\0null"],
            ['from_name' => "Panel\r\nHeader"], ['from_name' => str_repeat('n', 121)], ['from_address' => 'unapproved@example.test'],
            ['from_address' => 'not-an-email'], ['from_address' => "verified-sender@example.test\r\nBcc: bad@example.test"],
            ['reply_to' => 'not-an-email'], ['reply_to' => "reply@example.test\r\nCc: bad@example.test"]] as $invalid) {
            $this->postJson($url, array_replace($this->previewInput([$recipient->id]), $invalid))->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $preview = $this->postJson($url, $this->previewInput([$recipient->id]))->assertOk();
        $this->postJson($preview->json('send_url'), array_replace($this->composition(), ['subject' => 'Changed subject']))->assertConflict();
        $this->postJson($preview->json('send_url'), array_replace($this->composition(), ['message' => 'Changed message']))->assertConflict();
        foreach (['from_name' => 'Different panel', 'from_address' => 'default-sender@example.test', 'reply_to' => 'different-replies@example.test'] as $field => $value) {
            $this->postJson($preview->json('send_url'), array_replace($this->composition(), [$field => $value]))->assertConflict();
        }
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Mail::assertNothingSent();
        Bus::assertNothingDispatched();
    }
}
