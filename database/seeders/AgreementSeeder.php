<?php

namespace Database\Seeders;

use App\Models\Agreement;
use Illuminate\Database\Seeder;

class AgreementSeeder extends Seeder
{
    public function run(): void
    {
        Agreement::create([
            'title' => 'Code of Conduct',
            'version' => 'v1',
            'content' => '
<h2>Cape Tennis Code of Conduct</h2>

<p>Welcome to Cape Tennis. By participating in our events, you agree to abide by the following Code of Conduct.</p>

<h3>1. Sportsmanship</h3>
<p>All players are expected to:</p>
<ul>
    <li>Treat opponents, officials, and spectators with respect</li>
    <li>Accept all decisions of officials gracefully</li>
    <li>Avoid unsportsmanlike conduct including verbal abuse, racket abuse, or any form of cheating</li>
</ul>

<h3>2. Punctuality</h3>
<p>Players must arrive at least 15 minutes before their scheduled match time. Failure to appear may result in a default.</p>

<h3>3. Dress Code</h3>
<p>Players must wear appropriate tennis attire. Non-marking tennis shoes are required on all courts.</p>

<h3>4. Court Etiquette</h3>
<ul>
    <li>Wait until a point is completed before crossing behind a court</li>
    <li>Return stray balls promptly and safely</li>
    <li>Keep noise to a minimum during play</li>
</ul>

<h3>5. Mobile Phones</h3>
<p>Mobile phones must be set to silent mode during matches. Players may not use phones during play.</p>

<h3>6. Withdrawal Policy</h3>
<p>Players who need to withdraw from an event should contact <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a> as soon as possible.</p>

<h3>7. Disciplinary Action</h3>
<p>Violation of this Code of Conduct may result in:</p>
<ul>
    <li>Warning</li>
    <li>Point penalty</li>
    <li>Game penalty</li>
    <li>Match default</li>
    <li>Suspension from future events</li>
</ul>

<h3>8. Acknowledgement</h3>
<p>By accepting this Code of Conduct, I agree to abide by all rules and regulations set forth by Cape Tennis. I understand that violations may result in disciplinary action.</p>
            ',
            'is_active' => 1,
        ]);
    }
}
