<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventTransactionSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_summary_excludes_clothing_receipts_fees_and_refunds_from_registration(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        EventAdmin::create(['user_id' => $user->id, 'event_id' => $event->id]);
        $row = fn (array $values) => (object) array_merge([
            'event_id' => $event->id, 'type' => 'payment', 'subtype' => null,
            'gross' => 0, 'fee' => 0, 'capeFee' => 0, 'net' => 0,
            'created_at' => now(), 'order' => null, 'player' => 'Test player',
            'entryCount' => 1, 'refund_gross' => 0, 'registrationDetails' => [],
        ], $values);
        $payments = collect([
            $row(['gross' => 100, 'fee' => -5, 'capeFee' => -10, 'net' => 85]),
            $row(['type' => 'clothing_payment', 'gross' => 200, 'fee' => -8, 'net' => 192, 'registrationDetails' => [['player' => 'Clothing player', 'item' => 'Regional shirt', 'size' => 'Medium', 'price' => 200]]]),
        ]);
        $refunds = collect([
            $row(['type' => 'refund', 'subtype' => 'clothing_refund', 'gross' => -50, 'refund_gross' => 50, 'refund_fee' => 2, 'refund_net' => 48, 'fee' => 2, 'net' => -48, 'refund_status' => 'completed']),
        ]);
        $ledger = app(FinancialLedgerService::class);
        $built = ['paymentRows' => $payments, 'refundRows' => $refunds, 'payoutRows' => collect(),
            'totals' => $ledger->buildTotals($payments, $refunds, collect())];
        $this->mock(FinancialLedgerService::class)->shouldReceive('buildForEvent')->twice()->andReturn($built);

        $response = $this->actingAs($user)->get(route('admin.events.transactions', $event))
            ->assertSuccessful()
            ->assertViewHas('totalEntries', 1)
            ->assertViewHas('totalGross', 100)
            ->assertViewHas('totalPayfastFees', -5)
            ->assertViewHas('totalCapeTennisFees', -10)
            ->assertViewHas('refundCount', 0)
            ->assertViewHas('netTournamentIncome', 85)
            ->assertViewHas('receiptSections', fn ($sections) => $sections['clothing']['totals']['net'] === 144.0)
            ->assertSee('Regional clothing funds')
            ->assertSee('Clothing refund')
            ->assertSee('"mode":"clothing_summary"', false)
            ->assertSee('"item":"Regional shirt"', false)
            ->assertSee('"size":"Medium"', false)
            ->assertSee('Net clothing funds for region')
            ->assertSee('"clothing_fee":"2.00"', false)
            ->assertSee('"clothing_net":"48.00"', false)
            ->assertDontSee('Net to Event</th>', false);

        $pdfHtml = view('backend.adminPage.pdf.transactions', [
            'event' => $event,
            'ledger' => $payments->merge($refunds),
            'receiptSections' => $response->viewData('receiptSections'),
            'totalPayouts' => 0,
        ])->render();
        $this->assertStringContainsString('Registration reconciliation', $pdfHtml);
        $this->assertStringContainsString('Net payable to convenor', $pdfHtml);
        $this->assertStringContainsString('Clothing refund', $pdfHtml);
        $this->assertStringContainsString('R144.00', $pdfHtml);
        $this->assertStringNotContainsString('<th>Balance</th>', $pdfHtml);

        $pdfResponse = $this->get(route('transactions.pdf', $event))
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());
    }
}
