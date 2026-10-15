<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, Event, EventType, RegistrationOrder, RegistrationOrderItems, TeamPaymentOrder, TrialProgramme};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrialEftCheckoutViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'trial_checkout_view', 'database.connections.trial_checkout_view' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        Schema::create('trial_programmes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->text('bank_details')->nullable();
            $table->timestamps();
        });
    }

    public function test_team_and_empty_registration_orders_render_without_registration_items(): void
    {
        $this->assertStringNotContainsString('Pay by EFT', $this->renderOrder(new TeamPaymentOrder()));
        $order = new RegistrationOrder();
        $order->setRelation('items', collect());
        $this->assertStringNotContainsString('Pay by EFT', $this->renderOrder($order));
    }

    public function test_trial_checkout_uses_only_its_own_programme_and_exact_amount(): void
    {
        TrialProgramme::create(['event_id' => 41, 'bank_details' => 'Correct regional account']);
        TrialProgramme::create(['event_id' => 42, 'bank_details' => 'Other private account']);
        $html = $this->renderOrder($this->orderForEvent(41));
        $this->assertStringContainsString('Correct regional account', $html);
        $this->assertStringContainsString('R 123.45', $html);
        $this->assertStringContainsString('Upload proof for verification', $html);
        $this->assertStringNotContainsString('Other private account', $html);
        $this->assertStringNotContainsString('Pay by EFT', $this->renderOrder($this->orderForEvent(43)));
    }

    public function test_paid_and_in_flight_orders_preserve_payment_gates(): void
    {
        TrialProgramme::create(['event_id' => 41, 'bank_details' => 'Correct regional account']);
        $order = $this->orderForEvent(41);
        $order->pay_status = 1;
        $this->assertStringNotContainsString('Pay by EFT', $this->renderOrder($order));
        $order->pay_status = 0;
        $order->payfast_handed_off_at = now();
        $html = $this->renderOrder($order);
        $this->assertStringContainsString('Wait for it to resolve before using EFT', $html);
        $this->assertStringNotContainsString('Upload proof for verification', $html);
    }

    private function orderForEvent(int $eventId): RegistrationOrder
    {
        $event = new Event(['id' => $eventId]);
        $event->id = $eventId;
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
