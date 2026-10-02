<?php

namespace Tests\Feature;

use App\Models\BulkEmailLog;
use App\Models\User;
use App\Services\OutboundMailHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Message;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class SuperAdminMailHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('super-user', 'web');
        $user = User::factory()->create();
        $user->assignRole('super-user');

        return $user;
    }

    public function test_history_requires_super_user_and_guest_login(): void
    {
        $this->get(route('backend.superadmin.mail-history'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('backend.superadmin.mail-history'))->assertForbidden();
    }

    public function test_history_paginates_and_excludes_sensitive_content(): void
    {
        for ($i = 0; $i < 27; $i++) {
            BulkEmailLog::create(['mail_type' => 'event_announcement', 'recipient_email' => "person{$i}@example.test", 'status' => 'sent', 'payload' => ['rendered_subject' => "Notice {$i}", 'rendered_html' => 'PRIVATE_BODY_TOKEN'], 'error_message' => 'SECRET_TRANSPORT_PASSWORD']);
        }
        $this->actingAs($this->admin())->get(route('backend.superadmin.mail-history'))
            ->assertOk()->assertSee('Notice 26')->assertDontSee('Notice 0')
            ->assertDontSee('PRIVATE_BODY_TOKEN')->assertDontSee('SECRET_TRANSPORT_PASSWORD')
            ->assertSee('Sent — acceptance unverified')
            ->assertViewHas('mailLogs', fn ($logs) => $logs->total() === 27 && $logs->count() === 25 && $logs->first()->payload === null);
        $this->get(route('backend.superadmin.mail-history', ['mail_page' => 2]))->assertSee('Notice 0')->assertDontSee('Notice 26');
    }

    public function test_filters_and_acceptance_labels_are_truthful(): void
    {
        $accepted = BulkEmailLog::create(['mail_type' => 'event_announcement', 'recipient_email' => 'target@example.test', 'status' => 'sent', 'evidence_status' => 'server_accepted', 'accepted_at' => now(), 'payload' => ['subject' => 'Reviewed notice']]);
        BulkEmailLog::create(['mail_type' => 'system_mail', 'recipient_email' => 'other@example.test', 'status' => 'failed']);
        $this->actingAs($this->admin())->get(route('backend.superadmin.mail-history', ['mail_recipient' => 'target', 'mail_subject' => 'Reviewed', 'mail_type' => 'event_announcement', 'mail_status' => 'sent', 'mail_from' => now()->toDateString(), 'mail_until' => now()->toDateString()]))
            ->assertOk()->assertSee('Mail server accepted')->assertDontSee('other@example.test')
            ->assertViewHas('mailLogs', fn ($logs) => $logs->count() === 1 && $logs->first()->id === $accepted->id);
        $this->get(route('backend.superadmin.mail-history', ['mail_until' => now()->toDateString()]))->assertOk();
        $this->get(route('backend.superadmin.mail-history', ['mail_status' => 'delivered']))->assertSessionHasErrors('mail_status');
    }

    public function test_actual_laravel_mail_is_logged_metadata_only(): void
    {
        Mail::raw('DO_NOT_STORE_BODY', function (Message $mail) {
            $mail->to('primary@example.test')->cc('cc@example.test')->bcc('hidden@example.test')->subject('System notice');
        });
        $this->assertSame(3, BulkEmailLog::count());
        foreach (BulkEmailLog::all() as $log) {
            $this->assertSame('sent', $log->status);
            $this->assertSame('unverified', $log->evidence_status);
            $this->assertNull($log->accepted_at);
            $this->assertSame(['rendered_subject' => 'System notice'], $log->payload);
        }
    }

    public function test_transport_clone_correlates_and_existing_campaign_is_not_duplicated(): void
    {
        $tracker = app(OutboundMailHistory::class);
        $email = (new Email)->from('from@example.test')->to('to@example.test')->subject('Clone test')->text('body');
        $tracker->sending(new MessageSending($email));
        $sent = new SentMessage(new \Symfony\Component\Mailer\SentMessage(clone $email, Envelope::create($email)));
        $tracker->sent(new MessageSent($sent, ['message' => new Message($email)]));
        $this->assertSame('sent', BulkEmailLog::sole()->status);
        $tracker->sending(new MessageSending($email, ['outbound_mail_log_id' => BulkEmailLog::sole()->id]));
        $this->assertSame(1, BulkEmailLog::count());
    }

    public function test_interrupted_queue_attempt_does_not_overwrite_sent_or_previous_attempts(): void
    {
        $tracker = app(OutboundMailHistory::class);
        $email = (new Email)->from('from@example.test')->to('first@example.test')->subject('First')->text('body');
        $tracker->sending(new MessageSending($email));
        $tracker->resetAttempt();
        $tracker->sending(new MessageSending((clone $email)->to('second@example.test')));
        $tracker->interruptedAttempt();
        $this->assertSame('sending', BulkEmailLog::where('recipient_email', 'first@example.test')->sole()->status);
        $this->assertSame('acceptance_unknown', BulkEmailLog::where('recipient_email', 'second@example.test')->sole()->status);
    }

    public function test_logging_storage_failure_does_not_interrupt_mail(): void
    {
        BulkEmailLog::creating(function () { throw new \RuntimeException('Storage unavailable'); });
        Mail::raw('body', fn (Message $mail) => $mail->to('to@example.test')->subject('Fail open'));
        $this->assertSame(0, BulkEmailLog::count());
    }

    public function test_event_review_gate_holds_mail_before_global_tracking(): void
    {
        $event = new \App\Models\Event(['id' => 123]);
        $event->id = 123;
        $type = new \App\Models\EventType;
        $type->type = \App\Models\EventType::TEAM;
        $event->setRelation('eventTypeModel', $type);
        $this->mock(\App\Services\EventCommunicationService::class)
            ->shouldReceive('draftFixed')->once()->andReturn(new \App\Models\EventCommunicationBatch);
        $email = (new Email)->from('from@example.test')->to('to@example.test')->subject('Held event mail')->text('Review first');
        $result = \Illuminate\Support\Facades\Event::until(new MessageSending($email, ['review_event' => $event]));
        $this->assertFalse($result);
        $this->assertSame(0, BulkEmailLog::count());
    }

    public function test_successful_transport_survives_history_update_failure(): void
    {
        \Illuminate\Support\Facades\DB::connection()->beforeExecuting(function ($query) {
            if (str_starts_with(strtolower(ltrim($query)), 'update ') && str_contains($query, 'bulk_email_logs')) {
                throw new \RuntimeException('Storage unavailable');
            }
        });
        $result = Mail::raw('body', fn (Message $mail) => $mail->to('to@example.test')->subject('Update fail open'));
        $this->assertNotNull($result);
        $this->assertSame('sending', BulkEmailLog::sole()->status);
    }

    public function test_workspace_pagination_keeps_mail_tab_selected(): void
    {
        for ($i = 0; $i < 26; $i++) {
            BulkEmailLog::create(['mail_type' => 'system_mail', 'recipient_email' => "person{$i}@example.test", 'status' => 'sent']);
        }
        $request = \Illuminate\Http\Request::create('/backend/superadmin/workspace');
        $request->setRouteResolver(fn () => (new \Illuminate\Routing\Route('GET', 'backend/superadmin/workspace', fn () => null))->name('backend.superadmin.workspace'));
        $data = app(\App\Services\SuperAdminMailHistory::class)->data($request);
        $this->assertStringContainsString('tab=mails', $data['mailLogs']->nextPageUrl());
        $this->assertStringContainsString('mail_page=2', $data['mailLogs']->nextPageUrl());
    }

    public function test_summary_matches_filtered_records_and_distinguishes_acceptance_evidence(): void
    {
        $rows = [
            ['status' => 'sent', 'evidence_status' => 'server_accepted', 'accepted_at' => now()],
            ['status' => 'sent', 'evidence_status' => 'server_accepted'],
            ['status' => 'sent', 'evidence_status' => 'sandbox_accepted', 'accepted_at' => now()],
            ['status' => 'sent', 'evidence_status' => 'unverified'],
            ['status' => 'acceptance_unknown'], ['status' => 'sending'], ['status' => 'queued'], ['status' => 'failed'], ['status' => 'skipped'],
        ];
        foreach ($rows as $row) {
            BulkEmailLog::create($row + ['mail_type' => 'masters_invitation', 'recipient_email' => 'target@example.test',
                'payload' => ['rendered_subject' => 'Reviewed invitation']]);
        }
        BulkEmailLog::create(['status' => 'failed', 'mail_type' => 'system_mail', 'recipient_email' => 'other@example.test']);
        $this->actingAs($this->admin())->get(route('backend.superadmin.mail-history', [
            'mail_recipient' => 'target', 'mail_type' => 'masters_invitation', 'mail_subject' => 'Reviewed',
            'mail_from' => now()->toDateString(), 'mail_until' => now()->toDateString(),
        ]))->assertOk()->assertViewHas('mailSummary', fn ($summary) => $summary['total'] === 9
            && $summary['server_accepted'] === 1 && $summary['sandbox_accepted'] === 1 && $summary['unverified'] === 3
            && $summary['failed'] === 1 && $summary['sending'] === 1 && $summary['queued'] === 1 && $summary['skipped'] === 1)
            ->assertSee('Counts reflect current filters')->assertSee('Masters invitation')->assertDontSee('other@example.test');
        $this->get(route('backend.superadmin.mail-history', ['mail_recipient' => 'target', 'mail_status' => 'failed']))
            ->assertViewHas('mailSummary', fn ($summary) => $summary['total'] === 1 && $summary['failed'] === 1);
    }

    public function test_type_options_are_bounded_and_selected_type_and_pagination_are_preserved(): void
    {
        for ($i = 0; $i < 105; $i++) {
            BulkEmailLog::create(['mail_type' => sprintf('type_%03d', $i), 'recipient_email' => 'example@example.test', 'status' => 'queued']);
        }
        $request = \Illuminate\Http\Request::create('/backend/superadmin/workspace', 'GET', ['mail_type' => 'type_104', 'mail_status' => 'queued']);
        $request->setRouteResolver(fn () => (new \Illuminate\Routing\Route('GET', 'backend/superadmin/workspace', fn () => null))->name('backend.superadmin.workspace'));
        $data = app(\App\Services\SuperAdminMailHistory::class)->data($request);
        $this->assertTrue($data['mailTypesLimited']);
        $this->assertCount(101, $data['mailTypes']);
        $this->assertArrayHasKey('type_104', $data['mailTypes']);
        $this->assertSame(1, $data['mailSummary']['total']);
        $html = view('backend.superadmin.partials.mail-history', $data + ['errors' => new \Illuminate\Support\ViewErrorBag])->render();
        $this->assertStringContainsString('Showing the first 100 recorded mail types', $html);
        $this->assertStringNotContainsString('SECRET_TRANSPORT_PASSWORD', $html);
    }

    public function test_missing_subject_fallback_and_metadata_are_escaped_in_both_surfaces(): void
    {
        BulkEmailLog::create(['mail_type' => 'system_mail', 'recipient_email' => 'safe@example.test',
            'recipient_name' => '<script>alert("name")</script>', 'status' => 'sent',
            'payload' => ['rendered_html' => 'PRIVATE_BODY_TOKEN', 'signed_link' => 'PRIVATE_LINK_TOKEN'], 'error_message' => 'SECRET_ERROR_TOKEN']);
        BulkEmailLog::create(['mail_type' => 'event_announcement', 'recipient_email' => 'subject@example.test', 'status' => 'failed',
            'payload' => ['subject' => '<img src=x onerror=alert(1)>']]);
        $this->actingAs($this->admin());
        foreach ([route('backend.superadmin.mail-history'), route('backend.superadmin.workspace', ['tab' => 'mails'])] as $url) {
            $this->get($url)->assertOk()->assertSee('System email')->assertSee('Subject not recorded')
                ->assertSee('<script>alert("name")</script>')->assertSee('<img src=x onerror=alert(1)>')
                ->assertDontSee('<script>alert("name")</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false)
                ->assertDontSee('PRIVATE_BODY_TOKEN')->assertDontSee('PRIVATE_LINK_TOKEN')->assertDontSee('SECRET_ERROR_TOKEN');
        }
    }

    public function test_summary_and_responsive_history_render_with_synthetic_records_on_both_surfaces(): void
    {
        $statuses = ['sent', 'failed', 'queued', 'sending', 'acceptance_unknown', 'skipped'];
        for ($i = 0; $i < 27; $i++) {
            BulkEmailLog::create(['mail_type' => $i % 2 ? 'masters_invitation' : 'system_mail',
                'recipient_email' => $i === 26 ? 'very.long.synthetic.recipient.for.mobile@example.test' : "example{$i}@example.test",
                'recipient_name' => 'Example Recipient', 'status' => $statuses[$i % 6],
                'evidence_status' => $i % 6 === 0 ? 'server_accepted' : 'unverified',
                'accepted_at' => $i % 6 === 0 ? now() : null,
                'payload' => $i % 3 ? ['rendered_subject' => 'Wilson Masters — an example invitation and registration update'] : [],
            ]);
        }
        $this->actingAs($this->admin());
        foreach (['standalone' => route('backend.superadmin.mail-history'), 'workspace' => route('backend.superadmin.workspace', ['tab' => 'mails'])] as $surface => $url) {
            $response = $this->get($url)->assertOk()->assertSee('In progress')->assertSee('Outcome unverified')
                ->assertSee('data-label="Recipient"', false)->assertSee('mail_page=2', false)
                ->assertViewHas('mailSummary', fn ($summary) => $summary['total'] === 27);
            if (getenv('CT_MAIL_HISTORY_UI_PREVIEW') === '1') {
                $folder = storage_path('framework/testing/mail-history-preview');
                if (!is_dir($folder)) { mkdir($folder, 0777, true); }
                file_put_contents($folder.'/'.$surface.'.html', $response->getContent());
            }
        }
        $empty = $this->get(route('backend.superadmin.mail-history', ['mail_recipient' => 'no-match']))->assertOk()->assertSee('No logged emails match these filters.');
        if (getenv('CT_MAIL_HISTORY_UI_PREVIEW') === '1') {
            file_put_contents(storage_path('framework/testing/mail-history-preview/empty.html'), $empty->getContent());
        }
        $this->assertSame('Email', \App\Services\SuperAdminMailHistory::typeLabel(''));
        $this->assertSame('Email', \App\Services\SuperAdminMailHistory::typeLabel(null));
    }

    public function test_filtered_pagination_preserves_search_and_mail_tab_on_both_surfaces(): void
    {
        for ($i = 0; $i < 26; $i++) {
            BulkEmailLog::create(['mail_type' => 'system_mail', 'recipient_email' => "target{$i}@example.test", 'status' => 'queued', 'payload' => ['subject' => 'Example notice']]);
        }
        $this->actingAs($this->admin());
        $filters = ['mail_recipient' => 'target', 'mail_type' => 'system_mail', 'mail_status' => 'queued', 'mail_subject' => 'Example'];
        foreach (['backend.superadmin.mail-history', 'backend.superadmin.workspace'] as $route) {
            $params = $route === 'backend.superadmin.workspace' ? $filters + ['tab' => 'mails'] : $filters;
            $this->get(route($route, $params))->assertOk()->assertViewHas('mailLogs', function ($logs) use ($route, $filters) {
                parse_str(parse_url($logs->nextPageUrl(), PHP_URL_QUERY), $query);
                foreach ($filters as $key => $value) {
                    $this->assertSame($value, $query[$key]);
                }
                $this->assertSame('2', $query['mail_page']);
                if ($route === 'backend.superadmin.workspace') { $this->assertSame('mails', $query['tab']); }
                return $logs->total() === 26;
            });
        }
    }
}
