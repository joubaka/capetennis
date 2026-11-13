<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\EntryService;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\Event;
use App\Models\EventExpense;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FreeTestEntryArchiveTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Remove the reviewed pre-opening zero-money test history.';
    private const EVIDENCE = 'Operator verified this exact admin test never collected any cash, EFT, wallet or provider payment.';

    private User $actor;
    private Event $event;
    private CategoryEvent $category;
    private Player $player;
    private CategoryEventRegistration $entry;
    private Transaction $receipt;
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::findOrCreate('super-user', 'web');
        $this->actor = User::factory()->create()->assignRole('super-user');
        DB::table('eventtypes')->insertOrIgnore(['id' => 1, 'name' => 'Individual', 'type' => 1]);
        $this->event = Event::factory()->create(['entryFee' => 0, 'cape_tennis_fee' => 15]);
        $this->category = CategoryEvent::factory()->for($this->event)->create(['entry_fee' => null]);
        $this->player = Player::factory()->create();
        $service = app(EntryService::class);
        $this->entry = $service->addPlayerAsAdmin($this->category, $this->player->id, $this->actor);
        $service->withdrawEntryAsAdmin($this->entry, $this->actor);
        $this->receipt = Transaction::sole();
        DB::table('transactions_pf')->where('id', $this->receipt->id)->update(['cape_tennis_fee' => 15]);
        $this->receipt->refresh();
        EventExpense::create(['event_id' => $this->event->id, 'expense_type' => 'cape_tennis_fee',
            'description' => 'Cape Tennis fee (auto-synced)', 'amount' => 15, 'quantity' => 1, 'unit_price' => 15]);
        $this->ids = ['event_id' => $this->event->id, 'category_event_id' => $this->category->id,
            'player_id' => $this->player->id, 'entry_id' => $this->entry->id, 'receipt_id' => $this->receipt->id];
    }

    private function runArchive(?string $fingerprint = null, ?User $actor = null, ?array $ids = null): array
    {
        return app(EntryService::class)->archiveFreeTestHistory($actor ?? $this->actor, $ids ?? $this->ids,
            self::REASON, self::EVIDENCE, $fingerprint);
    }

    public function test_preview_is_read_only_and_apply_reversibly_archives_only_exact_event_test(): void
    {
        $otherEvent = Event::factory()->create(['entryFee' => 100]);
        $otherCategory = CategoryEvent::factory()->for($otherEvent)->create(['entry_fee' => 100]);
        $otherEntry = app(EntryService::class)->addPlayerAsAdmin($otherCategory, $this->player->id, $this->actor);
        $otherReceiptBefore = Transaction::where('event_id', $otherEvent->id)->sole()->getAttributes();
        DB::table('user_players')->insert(['user_id' => $this->actor->id, 'player_id' => $this->player->id]);
        $playerBefore = $this->player->fresh()->getAttributes();
        $registrationBefore = Registration::findOrFail($this->entry->registration_id)->getAttributes();
        $linksBefore = DB::table('player_registrations')->where('registration_id', $this->entry->registration_id)->get()->toJson();
        $preview = $this->runArchive();
        $this->assertTrue($preview['preview']);
        $this->assertSame(64, strlen($preview['fingerprint']));
        $this->assertSame(15.0, $preview['cape_fee_liability_to_archive']);
        $this->assertNull($this->entry->fresh()->deleted_at);
        $this->assertNull($this->receipt->fresh()->archived_at);
        $this->assertSame(0, Activity::where('log_name', 'maintenance')->count());

        $result = $this->runArchive($preview['fingerprint']);
        $this->assertFalse($result['preview']);
        $this->assertFalse($result['already_archived']);
        $this->assertNotNull($this->entry->fresh()->deleted_at);
        $this->assertNotNull($this->receipt->fresh()->archived_at);
        $this->assertSame('withdrawn', $this->entry->fresh()->status);
        $this->assertEquals(1, $this->entry->fresh()->payment_status_id);
        $this->assertEquals(15, $this->receipt->fresh()->cape_tennis_fee);
        $this->assertEquals(0, $this->receipt->fresh()->amount_gross);
        $this->assertSame($playerBefore, $this->player->fresh()->getAttributes());
        $this->assertSame($registrationBefore, Registration::findOrFail($this->entry->registration_id)->getAttributes());
        $this->assertSame($linksBefore, DB::table('player_registrations')->where('registration_id', $this->entry->registration_id)->get()->toJson());
        $this->assertSame($otherReceiptBefore, Transaction::where('event_id', $otherEvent->id)->sole()->getAttributes());
        $this->assertNotNull(CategoryEventRegistration::find($otherEntry->id));
        $this->assertDatabaseCount('players', 1);
        $this->assertDatabaseCount('user_players', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(0, EventExpense::where('event_id', $this->event->id)->count());
        $this->assertSame(0, CategoryEventRegistration::where('category_event_id', $this->category->id)->count());
        $ledger = app(FinancialLedgerService::class)->buildForEvent($this->event->fresh());
        $this->assertCount(0, $ledger['paymentRows']);
        $this->assertCount(0, $ledger['refundRows']);
        $this->assertEquals(0, $ledger['totals']['cape_fees']);
        $this->assertEquals(0, $ledger['totals']['pf_fees']);
        $audit = Activity::where('log_name', 'maintenance')->sole();
        $this->assertSame($this->entry->id, $audit->properties['before']['entry']['id']);
        $this->assertEquals(15, $audit->properties['before']['receipt']['cape_tennis_fee']);
        $this->assertCount(1, $audit->properties['before']['system_expenses']);
        $this->assertSame($this->actor->id, $audit->causer_id);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_exact_replay_is_idempotent_and_changed_archived_receipt_is_rejected(): void
    {
        $preview = $this->runArchive();
        $first = $this->runArchive($preview['fingerprint']);
        $again = $this->runArchive($preview['fingerprint']);
        $this->assertTrue($again['already_archived']);
        $this->assertSame($first['audit_id'], $again['audit_id']);
        $this->assertSame(1, Activity::where('log_name', 'maintenance')->count());
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertSame(1, CategoryEventRegistration::withTrashed()->count());
        DB::table('transactions_pf')->where('id', $this->receipt->id)->update(['amount_gross' => 100]);
        $this->expectException(ValidationException::class);
        $this->runArchive($preview['fingerprint']);
    }

    public function test_non_super_user_and_cross_event_ids_cannot_archive_anything(): void
    {
        foreach ([['actor' => User::factory()->create(), 'ids' => $this->ids],
            ['actor' => $this->actor, 'ids' => array_replace($this->ids, ['event_id' => Event::factory()->create()->id])],
            ['actor' => $this->actor, 'ids' => array_replace($this->ids, ['player_id' => Player::factory()->create()->id])],
            ['actor' => $this->actor, 'ids' => array_replace($this->ids, ['entry_id' => '1.5'])]] as $attempt) {
            try {
                $this->runArchive(null, $attempt['actor'], $attempt['ids']);
                $this->fail('Unsafe actor or identity must be rejected.');
            } catch (ValidationException) {
                $this->assertNull($this->entry->fresh()->deleted_at);
                $this->assertNull($this->receipt->fresh()->archived_at);
            }
        }
        $this->assertSame(0, Activity::where('log_name', 'maintenance')->count());
    }

    public function test_changed_review_is_rejected_without_partial_writes(): void
    {
        $preview = $this->runArchive();
        $this->event->update(['signUp' => false]);
        try {
            $this->runArchive($preview['fingerprint']);
            $this->fail('Stale preview should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('changed', $exception->getMessage());
        }
        $this->assertNull($this->entry->fresh()->deleted_at);
        $this->assertNull($this->receipt->fresh()->archived_at);
        $this->assertSame(1, EventExpense::where('event_id', $this->event->id)->count());
    }

    #[DataProvider('unsafeReceiptFields')]
    public function test_any_real_or_ambiguous_payment_evidence_is_rejected(string $field, mixed $value): void
    {
        DB::table('transactions_pf')->where('id', $this->receipt->id)->update([$field => $value]);
        $this->expectException(ValidationException::class);
        $this->runArchive();
    }

    public static function unsafeReceiptFields(): array
    {
        return [['amount_gross', 0.01], ['amount_fee', 0.01], ['amount_net', 0.01],
            ['pf_payment_id', 'PROVIDER-RECEIPT'], ['custom_int5', 15], ['custom_str5', 'TeamOrder']];
    }

    public function test_paid_private_collection_history_cannot_be_cleared_by_resetting_note(): void
    {
        $service = app(EntryService::class);
        $service->setAdminPaymentStatus($this->entry->fresh(), true, $this->actor);
        $service->setAdminPaymentStatus($this->entry->fresh(), false, $this->actor);
        $this->expectException(ValidationException::class);
        $this->runArchive();
    }

    public function test_an_order_reference_prevents_archiving_even_if_unpaid(): void
    {
        $order = RegistrationOrder::create(['user_id' => $this->actor->id, 'total_fee' => 0]);
        RegistrationOrderItems::forceCreate(['order_id' => $order->id, 'registration_id' => $this->entry->registration_id,
            'category_event_id' => $this->category->id, 'player_id' => $this->player->id, 'item_price' => 0]);
        $this->expectException(ValidationException::class);
        $this->runArchive();
    }

    public function test_competition_reference_prevents_archiving(): void
    {
        Draw::factory()->create(['event_id' => $this->event->id, 'category_event_id' => $this->category->id]);
        $this->expectException(ValidationException::class);
        $this->runArchive();
    }

    public function test_wallet_evidence_prevents_archiving(): void
    {
        $wallet = Wallet::factory()->forUser($this->actor)->create();
        WalletTransaction::create(['wallet_id' => $wallet->id, 'type' => 'credit', 'amount' => 1,
            'source_type' => 'admin_full_refund', 'source_id' => $this->entry->id]);
        $this->expectException(ValidationException::class);
        $this->runArchive();
    }

    public function test_shared_registration_and_approved_expense_are_both_protected(): void
    {
        $otherCategory = CategoryEvent::factory()->create();
        DB::table('category_event_registrations')->insert(['registration_id' => $this->entry->registration_id,
            'category_event_id' => $otherCategory->id]);
        try {
            $this->runArchive();
            $this->fail('Shared registration should prevent cleanup.');
        } catch (ValidationException) {
            $this->assertNull($this->entry->fresh()->deleted_at);
        }
        DB::table('category_event_registrations')->where('category_event_id', $otherCategory->id)->delete();
        EventExpense::where('event_id', $this->event->id)->firstOrFail()->update(['approved_at' => now(), 'approved_by' => $this->actor->id]);
        $this->expectException(ValidationException::class);
        $this->runArchive();
    }

    public function test_cli_is_preview_only_by_default_and_requires_reviewed_fingerprint_for_apply(): void
    {
        $args = ['event' => $this->event->id, 'category' => $this->category->id, 'player' => $this->player->id,
            'entry' => $this->entry->id, 'receipt' => $this->receipt->id,
            '--actor' => $this->actor->id, '--reason' => self::REASON, '--evidence' => self::EVIDENCE];
        $this->artisan('entries:archive-free-test', $args)->assertSuccessful();
        $this->assertNull($this->entry->fresh()->deleted_at);
        $this->artisan('entries:archive-free-test', $args + ['--apply' => true])->assertFailed();
        $preview = $this->runArchive();
        $this->artisan('entries:archive-free-test', $args + ['--apply' => true, '--fingerprint' => $preview['fingerprint']])->assertSuccessful();
        $this->assertNotNull($this->entry->fresh()->deleted_at);
    }

    #[DataProvider('inactiveEntryEvidence')]
    public function test_active_refund_or_payment_linked_entries_are_protected(string $field, mixed $value): void
    {
        DB::table('category_event_registrations')->where('id', $this->entry->id)->update([$field => $value]);
        $this->expectException(ValidationException::class);
        $this->runArchive();
    }

    public static function inactiveEntryEvidence(): array
    {
        return [['status', 'active'], ['refund_status', 'pending'], ['refund_gross', 1],
            ['wallet_transaction_id', 1], ['pf_transaction_id', 'PROVIDER-RECEIPT']];
    }

    #[DataProvider('competitionReferences')]
    public function test_standalone_practice_or_result_history_prevents_archiving(string $table, array $attributes): void
    {
        $attributes = array_map(fn ($value) => $value === 'registration' ? $this->entry->registration_id : $value, $attributes);
        DB::table($table)->insert($attributes);
        $this->expectException(ValidationException::class);
        $this->runArchive();
    }

    public static function competitionReferences(): array
    {
        return [
            ['fixture_results', ['winner_registration' => 'registration']],
            ['practice_fixtures', ['registration1_id' => 'registration', 'registration2_id' => 999]],
            ['practice_results', ['practice_fixture_id' => 999, 'winner_registration' => 'registration',
                'loser_registration' => 998, 'registration1_score' => 6, 'registration2_score' => 0]],
        ];
    }

    public function test_orphan_manual_receipt_protects_financial_evidence(): void
    {
        DB::table('registration_manual_receipts')->insert(['event_id' => $this->event->id, 'order_id' => 999,
            'amount' => 0.01, 'method' => 'eft', 'reference' => 'VERIFIED-OTHER-RECEIPT',
            'verified_by_user_id' => $this->actor->id, 'paid_at' => now()]);
        $this->expectException(ValidationException::class);
        $this->runArchive();
    }

    public function test_failure_after_archiving_receipt_rolls_back_all_changes_and_audit(): void
    {
        $preview = $this->runArchive();
        $this->partialMock(FinancialLedgerService::class, function ($mock) {
            $mock->shouldReceive('clearArchivedTestSystemExpenses')->once()->andThrow(new \RuntimeException('Injected failure'));
        });
        try {
            $this->runArchive($preview['fingerprint']);
            $this->fail('Expected injected ledger failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected failure', $exception->getMessage());
        }
        $this->assertNull($this->entry->fresh()->deleted_at);
        $this->assertNull($this->receipt->fresh()->archived_at);
        $this->assertSame(1, EventExpense::where('event_id', $this->event->id)->count());
        $this->assertSame(0, Activity::where('log_name', 'maintenance')->count());
    }

    public function test_legacy_receipt_without_registration_column_uses_exact_unique_event_category_player(): void
    {
        // DDL is transactional only in this disposable SQLite memory database.
        // Never alter a MySQL schema inside a RefreshDatabase test transaction.
        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Legacy schema simulation requires isolated SQLite :memory:.');
        }
        \Illuminate\Support\Facades\Schema::table('transactions_pf', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('registration_id');
        });
        $preview = $this->runArchive();
        $result = $this->runArchive($preview['fingerprint']);
        $this->assertFalse($result['already_archived']);
        $this->assertNotNull($this->entry->fresh()->deleted_at);
        $this->assertNotNull($this->receipt->fresh()->archived_at);
        $audit = Activity::where('log_name', 'maintenance')->sole();
        $this->assertSame('unique_event_category_player', $audit->properties['before']['receipt_registration_link']);
        $this->assertTrue($this->runArchive($preview['fingerprint'])['already_archived']);
    }
}
