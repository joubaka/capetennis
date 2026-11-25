<?php

namespace Tests\Unit;

use App\Models\Event;
use Tests\TestCase;

class RegistrationRefundTermsTest extends TestCase
{
    public function test_individual_terms_explain_deadline_fee_and_how_to_request_a_refund(): void
    {
        $event = new Event(['start_date' => '2027-01-20', 'withdrawal_deadline' => '2027-01-12 17:00:00']);
        $html = view('frontend.partials.registration-refund-terms', ['refundTermsEvent' => $event])->render();
        $this->assertStringContainsString('12 Jan 2027 17:00', $html);
        $this->assertStringContainsString('Choose <strong>Withdraw</strong>', $html);
        $this->assertStringContainsString('10% from the PayFast portion', $html);
        $this->assertStringContainsString('wallet contribution to the payer', $html);
        $this->assertStringContainsString('Pending</strong> does not mean money has been returned', $html);
        $this->assertStringContainsString('withdrawal alone does not return money', $html);
    }

    public function test_team_terms_use_the_team_fee_calculation(): void
    {
        $html = view('frontend.partials.registration-refund-terms', ['refundTermsFunding' => 'team'])->render();
        $this->assertStringContainsString('10% withdrawal fee from the amount paid', $html);
        $this->assertStringNotContainsString('10% from the PayFast portion', $html);
    }
}
