# CTR marketing portal

The super-user-only workspace is available at `/superadmin/marketing` through
the **CTR marketing** navigation link. This first version manages coach contacts,
plain-text campaign drafts and a financial projection. Campaigns have no send,
queue or WhatsApp-provider action. The hosting workflow below separately queues
operational proposal alerts to current super-users.

Contacts and drafts are paginated. Contact preferences record planning information,
not proof of consent. No player or coach data is imported automatically. Contact
interest status does not approve or certify an event. Opening a new campaign
offers an editable invitation template; it is saved only through **Save draft**.
Concurrent updates/deletions reject stale versions instead of overwriting changes.

## Campaign previews

Campaign editing includes branded email and illustrative WhatsApp tabs using
the configured Cape Tennis logo. Previews follow unsaved title, subject and body
edits in the browser. They do not save, send, generate an email or attach an image.
The WhatsApp card is a visual mockup, not an exported graphic. Actual delivery
and email-client compatibility have not been tested.

The text formatter supports simple bold emphasis, `##` headings and pipe-separated
tables (with or without surrounding pipes). The email heading uses its subject;
the WhatsApp card uses the first message line. The internal campaign name stays
in the editor workspace. All draft values enter the preview through text nodes or `textContent`;
HTML, links and scripts in draft copy are never interpreted as markup. Financial
tables come from the editable email text, not a second hardcoded calculator result.
Both previews preserve the original saved plain-text copy. Existing saved messages
are not rewritten when opened; new drafts offer a detailed email and a shorter
WhatsApp invitation separately.

## Stage One small-event projection

The initial template uses the publicly listed Panarottis/Spur Leg 3 categories
at `https://www.capetennis.co.za/events/260`: Red 16 at R200, Orange 16 at R250,
Green 12 at R250 and Yellow 8 at R300. These are editable planning defaults,
not a live event lookup or a change to event configuration.
The coach-facing calculator and default campaign copy call this **Stage One**:
a small event with 52 places across four categories. Panarottis remains the
historical source of the unchanged fees and capacities; the internal
`PanarottisCalculator` class name is retained for compatibility.

User-approved planning assumptions are 5% processing, R15 per paid entry for
Cape Tennis, a R500 morning convenor and ten courts for four hours at R50 per
court/hour. At full capacity, R12,600 gross leaves R8,690 after those costs,
before balls, prizes or further expenses. The 5% is not a verified provider tariff;
ten courts/four hours is not a validated schedule. Other expenses default to zero.

All amounts are calculated server-side in integer cents. Processing is rounded
half-up per entry, then multiplied by paid entries. Rental uses hundredths of
an hour and rounds the final rental total half-up to cents. Negative remaining
income is displayed as a projected loss. Calculator requests persist nothing and
never touch canonical payment records. Reset restores the full-capacity defaults.

Premium access, wider competition coverage and 2027 invitation events remain
proposals in the editable campaign copy; review them before future outreach.
Red, Orange and Green/Yellow pools remain separate.

## Local setup and verification

Required migration (do not run unrelated pending migrations):

`database/migrations/2026_11_19_150000_create_marketing_portal_tables.php`

The exact path is allowlisted in `deploy.config`; that is not deployment authority.
The migration creates only `marketing_contacts` and `marketing_campaigns`, with
no seed records. Campaign bodies use medium text to support the validated
30,000-character Unicode limit.

`tests/Feature/MarketingPortalTest.php` uses a private SQLite in-memory connection
and minimal shared-layout schema, never refreshing local or shared MySQL data.
It covers all endpoint access, contact CRUD/search/pagination/escaping, inert draft
CRUD, stale edits, Unicode storage, exact calculator totals and validation.
Set `CT_MARKETING_QA_HTML=1` to render fictional HTML fixtures for responsive QA.
`tests/Browser/marketing-campaign-preview.cjs` checks the real preview script,
local logo/styles, phone-to-desktop containment, editable financial tables,
unsaved updates, keyboard tabs and HTML-like injection remaining inert.

## Host proposal workflow

The trusted **I’d like to host an event** button in both preview layouts opens
`/host-an-event`; its URL is fixed by the application, never parsed from draft
copy. The landing page is public. Creating/submitting a proposal requires sign-in,
with five submission requests per hour and a server-issued, session-bound token.
The form captures organiser contact details, venue, dates, available courts and
requested category limits/fees. Requests are proposals, not authoritative event
settings; unknown role, owner, approval, publication and event identifiers are
ignored. A duplicate submission token returns the original request unchanged.

Each submission is pending. Only its applicant and super-users can view the
applicant status page; only super-users can access the paginated proposal inbox
and internal notes. Approval/rejection records the actual reviewer and time.
Repeated identical decisions are inert; opposing decisions after review conflict.
Approval does not certify, publish, create an event or grant organiser access.

After approval, **Prepare unpublished event** opens the existing canonical event
form with proposed name, dates, contact and venue prefilled. Saving locks the
approved proposal and creates/links one unpublished event with registration closed,
even if publication flags were forged. Replaying creation returns the same event.
No applicant is automatically assigned an event role. Categories, fees, courts,
deadlines and access must be configured and verified in the existing event
workspace. The normal event settings page provides the later explicit publication
action; this workflow does not silently publish or modify ordinary event creation.

Submission creates one notification record per current super-user and queues a
database-connection job after commit. The job uses `MailAccountManager` and the
existing `outbound-mail` limiter, rechecks the recipient’s current super-user role,
and claims the row before attempting transport. It sends only a proposal reference
and protected review link, not applicant contact data. Duplicate jobs do not send
again; ambiguous delivery failures become `needs_review` without automatic resend.
`queued` is not delivered, and an interrupted `sending` state also needs review.
If there are no super-user recipients, the proposal remains available in the inbox
with zero recorded alerts. No new mail or payment configuration is introduced.

Required additional migration:
`database/migrations/2026_11_19_170000_create_host_event_applications.php`.
It creates empty proposal and notification tables only; migration allowlisting
does not authorise production execution. Tests in `HostEventApplicationTest`
use private SQLite memory data, fake queue/mail and fictional browser fixtures
(`CT_HOST_QA_HTML=1`), including canonical unpublished-event creation and later
explicit publication of a fictional event. Local implementation verification must
not submit real proposals or drain their notification jobs.

### Automatic court suggestions

The applicant enters total available full-size courts and assigns courts to Red,
Orange, Green and Yellow. Suggested entries update automatically using an
authenticated, CSRF-protected JSON endpoint. Red uses 16 players per assigned
full-size court (four mini courts); each other category uses 4. Assigned courts
must total at least 1 and cannot exceed the available total. Zero omits a category.
There are no time/slot/contingency inputs, calculate button or fixed Stage One
entry caps. Two Red courts suggest 32 places; additional assigned courts raise
suggestions proportionately.

Defaults are 10 courts allocated 1/4/3/2, suggesting 16/16/12/8 players (52 total).
The financial reference therefore budgets 10 ×4 ×R50 = R2,000 court rental and
R8,690 remaining before balls, prizes and further expenses. Its rental hours
remain a budget input, not a proposed schedule duration.

The browser debounces allocation edits, aborts outdated requests and ignores
out-of-order responses. Pending or failed responses clear suggestions and disable
submission; organiser fields and fees remain unchanged. Valid responses update
suggestions and entry limits automatically. Applicants can then reduce limits in
whole groups of four. Fee and expense edits preserve those reduced limits;
allocation edits reset limits to the new suggestions. The server independently recomputes assignment ratios on
submission and rejects excessive/nonmultiple limits, over-allocation and forged
client totals. No estimate-review session state is required; the endpoint creates
no proposal, event, job or notification. Its rate-limit bucket is separate from
actual submission. The existing actor-bound form token is checked for both.

New proposals store a server-built court_plan model court-ratio-v2 in the existing
nullable JSON column. No migration is needed for this model revision. Earlier
four-player-rr-knockout-v1 snapshots are displayed as historical time-based plans
without rewriting them; null snapshots remain readable too. Historical v1 used
four-player groups, 7g−1 matches, contingency and per-court slot/elapsed bounds.
That superseded model is not used for new applications or automatic suggestions.

Court layout, safe Red mini-court setup, match formats, player rest and the actual
schedule remain subject to Cape Tennis review. Ratios do not prove a schedule.

### Live proposal finances

The host form renders a server-calculated financial table immediately and updates
it through the same authenticated JSON endpoint as court suggestions. Income uses
the requested entry limits, assuming those places are paid, rather than always
using the larger capacity suggestions. Per-category rows show entries, fee, gross,
processing, Cape Tennis and retained income. Editable planning expenses are the
convenor, rental hours/rate and other costs. Rental uses the sum of assigned courts,
not unassigned available courts; assigned courts still cost rental when their
category entry limit is reduced to zero.

`HostProposalProjection` reuses `PanarottisCalculator` integer-cent calculations.
Processing is fixed server-side at 5%, Cape Tennis at R15 per entry, regardless of
submitted overrides or totals. Both calculators support up to 1600 entries in a
category, matching the 100-Red-court planning bound. Default full 52 totals are
R12,600 gross, R630 processing, R780 Cape Tennis, R500 convenor, R2,000 rental and
R8,690 remaining. Unincluded expenses remain the host's responsibility. Negative
results display as **Projected loss**. All-zero entry limits can preview a loss
but cannot submit a proposal.

Court edits send use_suggested_limits=true; limit, fee and expense edits send false
unless a court update is still pending. A pending court reset survives intervening
edits and aborted requests until a current response successfully applies it. The
single debounced request stream uses abort/revision protection. Inputs stay
editable while requests run; only final submission is disabled, and stale financial
tables are hidden on pending or failed requests. Response rendering uses text
nodes, preserving input focus and all organiser details.

Submission ignores client totals/mode flags and recalculates from current limits,
fees and costs. Its court_plan snapshot includes financial_projection assumptions,
rows and totals for applicant and super-user review. Existing snapshots without
financial data remain readable. No migration, payment flow, receipt or ledger
mutation is involved. Tests cover exact cents, manually reduced limits, revised
fees/costs, assigned-only rent, maximum Red capacity, zero-entry loss, forged
commercial fees/totals, invalid monetary inputs and mocked browser request races.
