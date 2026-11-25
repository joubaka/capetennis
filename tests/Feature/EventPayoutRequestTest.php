<?php
namespace Tests\Feature;
use App\Domain\Finance\Services\EventPayoutRequestService;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventPayoutRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
class EventPayoutRequestTest extends TestCase {
    use RefreshDatabase;
    private Event $event;
    private User $admin;
    private User $super;
    private int $balance = 100001;
    private bool $pending = false;
    protected function setUp(): void {
        parent::setUp();
        foreach (['admin', 'super-user', 'convenor'] as $role) Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $this->admin = User::factory()->create(); $this->admin->assignRole('admin');
        $this->super = User::factory()->create(); $this->super->assignRole('super-user');
        $this->event = Event::factory()->create(['name' => 'Synthetic Payout Tournament', 'start_date' => today()->subDays(2), 'end_date' => today()->subDay(), 'status' => 'open', 'signUp' => false]);
        EventAdmin::create(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
        $this->mock(FinancialLedgerService::class)->shouldReceive('buildForEvent')->andReturnUsing(fn () => ['totals' => ['registration_balance' => $this->balance / 100], 'refundRows' => $this->pending ? collect([(object) ['type' => 'refund', 'refund_status' => 'pending', 'subtype' => 'registration_refund']]) : collect()]);
    }
    private function service(): EventPayoutRequestService { return app(EventPayoutRequestService::class); }
    private function payload(array $extra = []): array { return array_replace(['kind' => 'interim', 'amount' => '400.01', 'reason' => 'Synthetic court costs', 'request_token' => (string) Str::uuid(), 'preview_token' => $this->service()->token($this->service()->snapshot($this->event->fresh()))], $extra); }
    private function submit(array $data = []) { return $this->actingAs($this->admin)->post(route('admin.events.payout-requests.store', $this->event), $data ?: $this->payload()); }
    public function test_interim_retry_is_exact_audited_and_never_records_payment(): void {
        $data = $this->payload(); $this->submit($data)->assertRedirect(); $this->submit($data)->assertRedirect();
        $this->assertDatabaseCount('event_payout_requests', 1); $this->assertSame('400.01', EventPayoutRequest::sole()->amount);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'event.payout_requested')->count());
        $this->assertDatabaseCount('event_payouts', 0); $this->assertDatabaseCount('wallet_transactions', 0);
        $this->submit()->assertSessionHasErrors('request');
        $this->submit(array_replace($data, ['amount' => '400.02']))->assertSessionHasErrors('request');
    }
    public function test_only_assigned_admin_or_super_user_can_access_and_cross_event_isolation_holds(): void {
        $other = Event::factory()->create();
        $this->actingAs($this->admin)->get(route('admin.events.payout-requests', $other))->assertForbidden();
        $this->post(route('admin.events.payout-requests.complete', $other), ['confirmed' => 1])->assertForbidden();
        $this->post(route('admin.events.payout-requests.store', $other), $this->payload())->assertForbidden();
        $this->get(route('superadmin.payout-requests'))->assertForbidden();
        $ordinary = User::factory()->create(); EventAdmin::create(['event_id' => $this->event->id, 'user_id' => $ordinary->id]);
        $this->actingAs($ordinary)->get(route('admin.events.payout-requests', $this->event))->assertForbidden();
        $this->assertDatabaseCount('event_payout_requests', 0);
    }
    public function test_full_interim_and_final_before_explicit_completion_are_rejected(): void {
        $this->submit($this->payload(['amount' => '1000.01']))->assertSessionHasErrors('amount');
        $this->submit($this->payload(['kind' => 'final']))->assertSessionHasErrors('kind');
        $this->event->update(['status' => 'completed']);
        $this->submit($this->payload(['kind' => 'final']))->assertSessionHasErrors('kind');
        foreach (['0', '-1', '1.001'] as $amount) $this->submit($this->payload(['amount' => $amount]))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('event_payout_requests', 0);
    }
    public function test_completion_is_confirmed_idempotent_and_final_uses_server_amount(): void {
        $before = $this->event->fresh()->only(['published', 'results_published', 'signUp']);
        $url = route('admin.events.payout-requests.complete', $this->event);
        $this->actingAs($this->admin)->post($url)->assertSessionHasErrors('confirmed');
        $this->post($url, ['confirmed' => 1])->assertRedirect(); $this->post($url, ['confirmed' => 1])->assertRedirect();
        $this->assertDatabaseCount('event_payout_completions', 1);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'event.payout_completion')->count());
        $this->assertSame($before, $this->event->fresh()->only(['published', 'results_published', 'signUp']));
        $this->submit($this->payload(['kind' => 'final', 'amount' => '0.01']))->assertRedirect();
        $this->assertSame('1000.01', EventPayoutRequest::sole()->amount);
        $this->assertDatabaseCount('event_payouts', 0);
    }
    public function test_future_and_rescheduled_events_cannot_use_completion_attestation(): void {
        $this->event->update(['end_date' => today()->addDay()]);
        $this->actingAs($this->admin)->post(route('admin.events.payout-requests.complete', $this->event), ['confirmed' => 1])->assertSessionHasErrors('completion');
        $this->event->update(['end_date' => today()->subDay()]);
        $this->service()->complete($this->event, $this->admin, ['confirmed' => 1]);
        $this->event->update(['end_date' => today()->addDay()]);
        $this->submit($this->payload(['kind' => 'final']))->assertSessionHasErrors('kind');
    }
    public function test_pending_refunds_and_changed_previews_block_requests(): void {
        $data = $this->payload(); $this->balance--;
        $this->submit($data)->assertSessionHasErrors('request');
        $this->pending = true; $this->submit()->assertSessionHasErrors('request');
        $this->assertDatabaseCount('event_payout_requests', 0);
    }
    public function test_review_has_no_financial_effect_and_close_releases_outstanding_request(): void {
        $this->submit(); $request = EventPayoutRequest::sole();
        $url = route('superadmin.payout-requests.review', [$this->event, $request]);
        $review = ['status' => 'reviewed', 'review_note' => 'Synthetic review only', 'preview_token' => $this->payload()['preview_token']];
        $this->actingAs($this->admin)->post($url, $review)->assertForbidden();
        $foreign = Event::factory()->create();
        $this->actingAs($this->super)->post(route('superadmin.payout-requests.review', [$foreign, $request]), $review)->assertNotFound();
        $this->post($url, $review)->assertRedirect(); $this->post($url, $review)->assertRedirect();
        $this->assertSame('reviewed', $request->fresh()->status);
        $this->submit()->assertSessionHasErrors('request');
        $this->actingAs($this->super)->post($url, array_replace($review, ['status' => 'closed', 'review_note' => 'Separate reconciliation reference recorded; no payment claimed']))->assertRedirect();
        $this->submit()->assertRedirect(); $this->assertDatabaseCount('event_payout_requests', 2);
        $this->assertDatabaseCount('event_payouts', 0); $this->assertDatabaseCount('wallet_transactions', 0);
    }
    public function test_stale_review_and_disabled_audit_fail_closed(): void {
        $this->submit(); $request = EventPayoutRequest::sole(); $this->balance--;
        $this->actingAs($this->super)->post(route('superadmin.payout-requests.review', [$this->event, $request]), ['status' => 'reviewed', 'review_note' => 'Review', 'preview_token' => $this->payload()['preview_token']])->assertSessionHasErrors('request');
        config(['audit.enabled' => false]);
        try { $this->service()->complete($this->event, $this->admin, ['confirmed' => 1]); $this->fail('Audit must be required'); } catch (\RuntimeException $e) { $this->assertStringContainsString('audit', $e->getMessage()); }
        $this->assertDatabaseCount('event_payout_completions', 0);
    }
    public function test_event_bound_preview_reopening_and_request_audit_rollback(): void {
        $other = Event::factory()->create();
        $this->assertNotSame($this->payload()['preview_token'], $this->service()->token($this->service()->snapshot($other)));
        $this->service()->complete($this->event, $this->admin, ['confirmed' => 1]);
        $this->event->refresh()->update(['status' => 'open']);
        $this->submit($this->payload(['kind' => 'final']))->assertSessionHasErrors('kind');
        config(['audit.enabled' => false]);
        try { $this->service()->request($this->event->fresh(), $this->admin, $this->payload()); $this->fail('Request audit must be required'); } catch (\RuntimeException $e) { $this->assertStringContainsString('audit', $e->getMessage()); }
        $this->assertDatabaseCount('event_payout_requests', 0);
    }
    public function test_original_request_is_immutable(): void {
        $this->submit(); $request = EventPayoutRequest::sole();
        try { $request->update(['amount' => '1.00']); $this->fail('Original request must be immutable'); } catch (\LogicException $e) { $this->assertStringContainsString('immutable', $e->getMessage()); }
        $this->assertSame('400.01', $request->fresh()->amount);
    }
    public function test_canonical_ledger_deducts_recorded_payouts_and_excludes_clothing(): void {
        $this->app->forgetInstance(FinancialLedgerService::class);
        $ledger = $this->partialMock(FinancialLedgerService::class);
        $ledger->shouldReceive('buildPaymentRows')->andReturn(collect([
            (object) ['type' => 'payment', 'gross' => 1000.01, 'fee' => 0, 'capeFee' => 0, 'net' => 1000.01],
            (object) ['type' => 'clothing_payment', 'gross' => 500, 'fee' => 0, 'capeFee' => 0, 'net' => 500],
        ]));
        $ledger->shouldReceive('buildRefundRows')->andReturn(collect());
        \App\Models\EventPayout::create(['event_id' => $this->event->id, 'amount' => '200.01', 'paid_by' => $this->super->id, 'paid_at' => now()]);
        $this->assertSame(80000, $this->service()->snapshot($this->event)['balance_cents']);
        $this->submit($this->payload(['amount' => '800.00']))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('event_payout_requests', 0);
    }
    public function test_pages_render_and_export_synthetic_browser_fixtures(): void {
        $this->actingAs($this->admin)->get(route('admin.events.payout-requests', $this->event))->assertOk()->assertSee('Interim amount (rand)');
        $this->capture('interim');
        $this->service()->complete($this->event, $this->admin, ['confirmed' => 1]); $this->capture('final');
        $this->submit(); $this->capture('pending');
        $response = $this->actingAs($this->super)->get(route('superadmin.payout-requests'))->assertOk()->assertSee('Synthetic Payout Tournament');
        if (getenv('PAYOUT_CAPTURE')) file_put_contents(storage_path('framework/testing/payout-queue.html'), $response->getContent());
    }
    private function capture(string $name): void {
        $response = $this->actingAs($this->admin)->get(route('admin.events.payout-requests', $this->event))->assertOk();
        if (getenv('PAYOUT_CAPTURE')) { @mkdir(storage_path('framework/testing'), 0777, true); file_put_contents(storage_path('framework/testing/payout-'.$name.'.html'), $response->getContent()); }
    }
}
