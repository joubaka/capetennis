<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, EventType, RegistrationOrder, RegistrationOrderItems, TeamPaymentOrder, TrialProgramme, TrialRankingRun};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialEftCheckoutViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_and_empty_registration_orders_render_without_registration_items(): void
    {
        $this->assertStringNotContainsString('Pay by EFT', $this->renderOrder(new TeamPaymentOrder()));
        $order = new RegistrationOrder();
        $order->setRelation('items', collect());
        $this->assertStringNotContainsString('Pay by EFT', $this->renderOrder($order));
    }

    public function test_trial_checkout_uses_only_its_own_programme_and_exact_amount(): void
    {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $withoutProgramme = Event::factory()->create();
        TrialProgramme::create(['event_id' => $event->id, 'bank_details' => 'Correct regional account']);
        TrialProgramme::create(['event_id' => $otherEvent->id, 'bank_details' => 'Other private account']);
        $html = $this->renderOrder($this->orderForEvent($event));
        $this->assertStringContainsString('Correct regional account', $html);
        $this->assertStringContainsString('R 123.45', $html);
        $this->assertStringContainsString('Upload proof for verification', $html);
        $this->assertStringNotContainsString('Other private account', $html);
        $this->assertStringNotContainsString('Pay by EFT', $this->renderOrder($this->orderForEvent($withoutProgramme)));
    }

    public function test_paid_and_in_flight_orders_preserve_payment_gates(): void
    {
        $event = Event::factory()->create();
        TrialProgramme::create(['event_id' => $event->id, 'bank_details' => 'Correct regional account']);
        $order = $this->orderForEvent($event);
        $order->pay_status = 1;
        $this->assertStringNotContainsString('Pay by EFT', $this->renderOrder($order));
        $order->pay_status = 0;
        $order->payfast_handed_off_at = now();
        $html = $this->renderOrder($order);
        $this->assertStringContainsString('Wait for it to resolve before using EFT', $html);
        $this->assertStringNotContainsString('Upload proof for verification', $html);
    }

    public function test_programme_fee_and_selection_state_persist_after_rendering_checkout(): void
    {
        $event = Event::factory()->create();
        $programme = TrialProgramme::create(['event_id' => $event->id, 'bank_details' => 'Correct regional account']);
        $this->assertStringContainsString('Pay by EFT', $this->renderOrder($this->orderForEvent($event)));

        $run = TrialRankingRun::create(['event_id' => $event->id, 'signature' => hash('sha256', 'checkout isolation'), 'positions' => []]);
        $concludedAt = now()->startOfSecond();
        $programme->update([
            'participation_fee' => '123.45',
            'current_run_id' => $run->id,
            'concluded_at' => $concludedAt,
            'manual_position_categories' => [17, 23],
        ]);

        $programme->refresh();
        $this->assertSame('123.45', $programme->participation_fee);
        $this->assertSame($run->id, $programme->current_run_id);
        $this->assertTrue($concludedAt->equalTo($programme->concluded_at));
        $this->assertSame([17, 23], $programme->manual_position_categories);
        $this->assertDatabaseCount('trial_programmes', 1);
        $this->assertDatabaseCount('trial_ranking_runs', 1);
    }

    private function orderForEvent(Event $event): RegistrationOrder
    {
        $type = new EventType();
        $type->code = EventType::INTERPROVINCIAL_TRIALS_CODE;
        $event->setRelation('eventTypeModel', $type);
        $category = new CategoryEvent();
        $category->setRelation('event', $event);
        $item = new RegistrationOrderItems();
        $item->setRelation('category_event', $category);
        $order = new RegistrationOrder(['total_fee' => 123.45, 'pay_status' => 0]);
        $order->id = 91;
        $order->setRelation('items', collect([$item]));

        return $order;
    }

    private function renderOrder($order): string
    {
        return view('frontend.event.partials.trial-eft-checkout', compact('order'))->render();
    }
}
