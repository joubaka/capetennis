<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\{BulkEmailLog, Event, EventAdmin, EventConvenor, EventExpense, User};
use App\Services\EventFinanceReportDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, Mail};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventFinanceReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([SendBulkEmailJob::class]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
        $this->event = Event::factory()->create(['name' => 'Report Test Event']);
        EventAdmin::create(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        $director = EventConvenor::create(['event_id' => $this->event->id, 'user_id' => $this->admin->id, 'role' => 'hoof', 'profit_share_pct' => 100]);
        EventExpense::create(['event_id' => $this->event->id, 'expense_type' => 'Equipment', 'description' => 'Synthetic equipment', 'quantity' => 2, 'unit_price' => 61.75, 'amount' => 123.50, 'paid_by_convenor_id' => $director->id]);
    }

    public function test_pdf_and_html_use_the_same_figures_and_export_does_not_sync_expenses(): void
    {
        $foreign = Event::factory()->create();
        EventExpense::create(['event_id' => $foreign->id, 'expense_type' => 'Equipment', 'amount' => 9999, 'description' => 'Foreign expense']);
        $pdfData = null;
        \Illuminate\Support\Facades\View::composer('backend.event.pdf.finance-breakdown', function ($view) use (&$pdfData) {
            $pdfData = $view->getData();
        });
        $before = EventExpense::count();
        $pdf = $this->actingAs($this->admin)->get(route('admin.events.finances.report.pdf', $this->event));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertSame($before, EventExpense::count());
        $this->assertEquals(123.50, $pdfData['totalExpenses']);
        $this->assertEquals(-123.50, $pdfData['netProfit']);
        $this->assertCount(1, $pdfData['expenses']);
        $html = $this->get(route('admin.events.finances', $this->event));
        $html->assertOk()->assertSee('Download PDF')->assertSee('Email report');
        $this->assertEquals(123.50, $html->viewData('totalExpenses'));
        $this->assertEquals(-123.50, $html->viewData('netProfit'));
        if (getenv('CT_FINANCE_REPORT_QA') === '1') {
            $path = storage_path('app/finance-report-qa');
            if (!is_dir($path)) mkdir($path, 0777, true);
            file_put_contents($path.'/breakdown.pdf', $pdf->getContent());
            file_put_contents($path.'/finance.html', $html->getContent());
        }
    }

    public function test_report_requires_event_finance_access_for_both_endpoints(): void
    {
        $other = Event::factory()->create();
        $this->get(route('admin.events.finances.report.pdf', $this->event))->assertRedirect('login');
        $this->actingAs($this->admin)->get(route('admin.events.finances.report.pdf', $other))->assertForbidden();
        $this->postJson(route('admin.events.finances.report.email', $other), $this->input())->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.events.finances.report.pdf', $this->event))->assertForbidden();
        $this->postJson(route('admin.events.finances.report.email', $this->event), $this->input())->assertForbidden();
        Bus::assertNothingDispatched();
    }

    public function test_email_validates_recipient_and_is_idempotent_for_a_request(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.events.finances.report.email', $this->event), ['recipient' => 'invalid'])->assertUnprocessable();
        $input = $this->input();
        $this->postJson(route('admin.events.finances.report.email', $this->event), $input)->assertOk();
        $this->postJson(route('admin.events.finances.report.email', $this->event), $input)->assertOk();
        $this->assertSame(1, BulkEmailLog::where('mail_type', 'event_finance_report')->count());
        Bus::assertDispatchedTimes(SendBulkEmailJob::class, 1);
        $this->postJson(route('admin.events.finances.report.email', $this->event), [...$input, 'recipient' => 'other@example.test'])->assertConflict();
        $log = BulkEmailLog::where('mail_type', 'event_finance_report')->firstOrFail();
        $mail = app(EventFinanceReportDelivery::class)->authorizedMailable($log);
        $this->assertNotNull($mail);
        $this->assertSame('application/pdf', $mail->rawAttachments[0]['options']['mime']);
        $payload = $log->payload;
        $payload['pdf'] = base64_encode('%PDF-tampered');
        $log->payload = $payload;
        $this->assertNull(app(EventFinanceReportDelivery::class)->authorizedMailable($log));
        $log->refresh();
        $log->recipient_email = 'other@example.test';
        $this->assertNull(app(EventFinanceReportDelivery::class)->authorizedMailable($log));
        $log->refresh();
        EventAdmin::where('user_id', $this->admin->id)->delete();
        EventConvenor::where('user_id', $this->admin->id)->delete();
        $this->assertNull(app(EventFinanceReportDelivery::class)->authorizedMailable($log));
    }

    public function test_managed_mail_listener_allows_exact_pdf_snapshot_for_a_team_event(): void
    {
        $teamType = \App\Models\EventType::unguarded(fn () => \App\Models\EventType::create(['name' => 'Team', 'type' => \App\Models\EventType::TEAM]));
        $this->event->update(['eventType' => $teamType->id]);
        $this->actingAs($this->admin)->postJson(route('admin.events.finances.report.email', $this->event), $this->input())->assertOk();
        $log = BulkEmailLog::where('mail_type', 'event_finance_report')->firstOrFail();
        $log->update(['status' => 'sending']);
        config(['mail.default' => 'array']);
        Mail::mailer('array')->to($log->recipient_email)->send(app(EventFinanceReportDelivery::class)->authorizedMailable($log));
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertCount(1, $messages[0]->getOriginalMessage()->getAttachments());
        $changed = clone $messages[0]->getOriginalMessage();
        $changed->addTo('unapproved@example.test');
        try {
            app(\App\Listeners\RequireEventEmailReview::class)->handle(new \Illuminate\Mail\Events\MessageSending($changed, ['finance_report_log_id' => $log->id]));
            $this->fail('An extra recipient must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $changed = clone $messages[0]->getOriginalMessage();
        $changed->attach('tampered report', $log->payload['filename'], 'application/pdf');
        try {
            app(\App\Listeners\RequireEventEmailReview::class)->handle(new \Illuminate\Mail\Events\MessageSending($changed, ['finance_report_log_id' => $log->id]));
            $this->fail('A changed attachment must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    private function input(): array
    {
        return ['recipient' => 'reports@example.test', 'request_key' => (string) Str::uuid()];
    }
}
