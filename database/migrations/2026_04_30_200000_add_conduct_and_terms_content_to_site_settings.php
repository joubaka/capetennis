<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $defaultConductContent = '<h4>Cape Tennis Code of Conduct</h4>
<p>All players, coaches, parents, and spectators are expected to uphold the highest standards of sportsmanship and respect at all Cape Tennis events.</p>
<ol>
  <li><strong>Respect</strong> – Treat all players, officials, and staff with courtesy and respect at all times.</li>
  <li><strong>Fair Play</strong> – Play fairly and honestly at all times.</li>
  <li><strong>Integrity</strong> – Accept the decisions of officials gracefully and without dispute.</li>
  <li><strong>Conduct</strong> – Avoid offensive language, aggressive behaviour, or unsportsmanlike conduct.</li>
  <li><strong>Responsibility</strong> – Parents and guardians are responsible for the conduct of minor players.</li>
</ol>
<p>Violation of this Code of Conduct may result in disqualification or suspension from future events.</p>';

    private string $defaultTermsContent = '<h4>Cape Tennis Terms &amp; Conditions</h4>
<p>By registering for a Cape Tennis event, you agree to the following terms and conditions.</p>
<ol>
  <li><strong>Registration</strong> – All registrations are subject to availability and confirmation by the event convenor.</li>
  <li><strong>Fees</strong> – Entry fees must be paid in full before participation is confirmed.</li>
  <li><strong>Withdrawals</strong> – Withdrawal requests must be submitted within the deadline period. Refunds are subject to the event\'s withdrawal policy.</li>
  <li><strong>Liability</strong> – Cape Tennis and event organisers are not liable for any injury, loss, or damage incurred during participation.</li>
  <li><strong>Photography</strong> – By participating, you consent to photographs or videos being taken and used for promotional purposes.</li>
  <li><strong>Privacy</strong> – Your personal information is collected and used in accordance with our Privacy Policy.</li>
</ol>
<p>Cape Tennis reserves the right to amend these terms at any time. Continued participation constitutes acceptance of the current terms.</p>';

    public function up(): void
    {
        DB::table('site_settings')->insert([
            'key'        => 'code_of_conduct_content',
            'value'      => $this->defaultConductContent,
            'label'      => 'Code of Conduct Content',
            'group'      => 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('site_settings')->insert([
            'key'        => 'terms_conditions_content',
            'value'      => $this->defaultTermsContent,
            'label'      => 'Terms & Conditions Content',
            'group'      => 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('site_settings')->whereIn('key', [
            'code_of_conduct_content',
            'terms_conditions_content',
        ])->delete();
    }
};
