<?php

namespace App\Services\Marketing;

class HostCampaignTemplate
{
    public static function whatsappBody(): string
    {
        return <<<'TEXT'
🎾 *COACHES: HOST CTR EVENTS & GENERATE FUNDS*

Run small tournaments, grow your academy's competition programme and help players progress from Red to Orange to Green and beyond.

✅ Earn income from hosting
✅ Understand players' levels beyond your academy
✅ Support development and camp nominations with competitive evidence

*Stage One: small event at full capacity*
52 places across four categories.
Red: 16 × R200 = R3,200
Orange: 16 × R250 = R4,000
Green: 12 × R250 = R3,000
Yellow: 8 × R300 = R2,400
*52 entries = R12,600*

Less 5% processing R630; Cape Tennis R15/entry R780; convenor R500; rental 10 courts × 4h × R50 = R2,000.
*Academy left: R8,690* before balls, prizes and other expenses.
Projection assumes all entries paid. Court schedule and provider fees need confirmation.

Players from any academy—or no academy—can enter. Basic CTR is intended to be free; entry fees are separate. Optional premium insights are planned. Red, Orange and Green/Yellow ratings stay separate.

Approved events and qualifying verified results must be recorded on Cape Tennis for CTR. Proposed 2027 elite invitation events: dates and selection rules still to be confirmed.

*Interested? Use the hosting link below to create your event proposal. Cape Tennis reviews it before publication.*
TEXT;
    }

    public static function body(): string
    {
        return <<<'TEXT'
COACHES: HOST CTR TOURNAMENTS & GENERATE FUNDS

Cape Tennis is developing small, official CTR-certified events for coaches and academies to host.

Generate funds, provide regular competition and help players progress from Red to Orange to Green and onward. Recorded results can support development discussions and opportunities such as Cavaliers practice-camp nominations.

Players do not need to belong to your academy, or any academy. They need a Cape Tennis profile and must meet event eligibility requirements. Basic CTR is intended to be free to earn and view; event entry fees apply separately. An optional premium package is planned for deeper insights, development guidance and eligible player comparisons. Features, pricing and launch availability remain subject to confirmation.

Red, Orange and Green/Yellow have separate rating pools. CTR supports coaching judgment; limited evidence may be incomplete.

## Stage One: small event at full capacity

The Stage One small-event template has 52 places across four categories.

| Category | Entries | Entry fee | Income |
| Red Ball | 16 | R200 | R3,200 |
| Orange Ball | 16 | R250 | R4,000 |
| Green Ball | 12 | R250 | R3,000 |
| Yellow Ball | 8 | R300 | R2,400 |
| Total | 52 | — | R12,600 |

| Cost | Amount |
| Proposed processing allowance (5%) | R630 |
| Cape Tennis (R15 per paid entry) | R780 |
| Convenor for the morning | R500 |
| Rental (10 courts x 4 hours x R50) | R2,000 |
| Academy left before balls, prizes and other expenses | R8,690 |

This is a projection, assuming all entries are paid. Court allocation and scoring must be checked against a realistic schedule. The 5% is a planning allowance, not a verified provider tariff.

Approved events must be hosted on Cape Tennis and qualifying results entered and verified there to count toward CTR.

Academy events will expand the intended network alongside Wilson, inter-districts, Platteland and Cavaliers competitions, subject to confirmed qualifying formats and results arrangements.

We are exploring elite invitation events in mid and/or late 2027, potentially including singles and doubles and the top 10 eligible CTR-ranked players per defined age division. Dates and qualification rules are not final. Hosting does not guarantee selection.

Interested in hosting? Click “I’d like to host an event” below, then choose “Create an event proposal”. Sign in and submit your proposed event, organiser, venue, dates, courts and categories. Cape Tennis reviews the proposal before an event is prepared and published.
TEXT;
    }
}
