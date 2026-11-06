# Player dashboard access and integration

## Relationships and sponsorship

Existing pivot and legacy owner relationships remain usable. They have not been
retroactively verified. `PlayerRelationshipAccess` denies new claims of unrelated
existing profiles by ordinary accounts. Staff-reviewed additions require an
admin or super-user actor, an explanation and a successful transactional audit.
Existing legacy owners may restore their pivot link. A failed audit rolls back
the addition. Existing self-unlink and other family accounts remain independent.

Super-users can link a player to their own account from My Tennis to open the
linked Profile. The action uses the audited staff link workflow and sends no
notification. The relationship is a normal player link and can affect recipients
of future player communications until it is removed.

Creating a genuinely new own profile differs from finding an existing profile
with matching identity fields. A duplicate match does not prove ownership.
Additional guardians need staff review through existing user management; the
application does not currently provide guardian invitations or send review mail.
Legacy GET association mutations are retired. Private profiles, contact lists
and personal result archives require an established relationship or appropriate
staff access; event-scoped staff workflows retain their own authorization.

Registration and Plus sponsorship remain independent of ownership. Completing
a sponsored roster claim does not grant a player link or permission to edit an
unrelated player's contacts. Public event results remain available through their
existing publication gates. Only the payer controls their orders and billing.
Sponsor offers expose purchase availability rather than private membership
expiry, billing type, suspension or staff-grant metadata.

The legacy personal team-results archive applies the same relationship check.
Ordinary related accounts receive only published, visible event/draw results,
with explicit name/score/event field allowlists rather than serialized opponent
profiles. Its response is limited to 100 recent rows and identifies truncation;
older published records remain available through event results.

## Standard and Plus views

Customer player pages share `x-player-workspace-header` and the versioned
`public/assets/css/player-workspace.css`. Use its title, eyebrow, subtitle and
heading props plus summary/actions/navigation slots instead of adding another
page-specific hero. Callers must authorise private navigation before supplying
that slot. Billing help is a compact disclosure with the payer-only renewal
instructions retained.

Standard linked accounts can see their dashboard entries, published matches,
results and official rankings, manage existing access and connect TeamSheet
when configured. Personal ratings, pathway, Playing Record, season comparison,
Opponent Insights and past-event rating reconstruction require the selected
player's active entitlement and relationship. Super-user previews do not create
ownership. Private navigation targets require that access gate. Standard accounts
use the same header and navigation positions with labelled marketing targets and
an illustrative locked snapshot; real private data is never rendered under blur.

Keep Activity and Plus Opponents separate. Activity owns the published schedule,
reminders and entry actions for related accounts. Opponents adds eligible match
preparation and private saved meetings under the selected player's Plus access.
Link from the schedule to Opponents when eligible; do not put private comparison
or head-to-head data in Activity. Profile completeness belongs beside Edit
profile; the shared tab header keeps the selected player and authorized badge.
The four locked player-view buttons open dedicated feature marketing pages,
with navigation back to the dashboard and membership availability. Those pages
check the existing relationship (or super-user preview authority) and never read
private history, records or pathway evidence; paid endpoints retain their gates.

My Tennis opens directly with one persistent blue header containing the player
selector, dashboard tabs, player-view links and a compact rating snapshot.
Embedded navigation omits Overview and Player rating because the dashboard
already supplies those tabs. On AJAX rating loads, the fragment's snapshot
updates that header and its repeated chrome is hidden. Historical month
selection can show a separate badge when it differs from the latest month;
identical current-month badges should not be repeated below the header.

The compact snapshot uses at most 12 whitelisted saved points, on a fixed 0–100
scale, with missing months and changed comparison groups preserved. It reuses
the authorised history read rather than calculating ratings or revealing source
evidence. Non-Plus requests do not read that history for their illustration.

The Plus Rating view opens on Rating overview, with the latest saved CTR first,
followed by the player's comparable positions and age-group Top 10. Super-users
see the full-list filters below the positions. Rating history links open the
history section directly. Opponent comparisons remain scoped to eligible
published fixtures and saved meetings.

Dedicated player views and Plus offer/order/history/details pages use
`PlayerWorkspaceContext` for the same header. Its selector contains at most 100
existing related profiles, retaining an explicitly selected related player.
The selector's labelled View action opens My Tennis; it never changes an order,
checkout player or payer. Route-bound players and the payer's order player remain
authoritative over query parameters. Generic billing pages accept a `player`
navigation query only for an existing relationship. Unrelated sponsors receive
neither a private snapshot nor private player-view links. Pathway and entitled
offer headers reuse the history already read for their page content/readiness.

Locked navigation carries an allowlisted feature key (`record`, `opponents`,
`pathway`, `events`, `rating`) and the selected player to My Tennis. Each click,
including repeats while the tab is active, reveals and focuses its specific
marketing explanation. These panels contain explanatory text only. Membership
availability links retain the named player even while new purchases are paused.

Playing Record is a curated saved-evidence view, not a complete career archive.
Past-event ratings reconstruct eligible Cape Tennis events. TeamSheet evidence
may contribute to a rating while its match details remain restricted. See
[Player Plus records](player-plus-records.md) for those source boundaries.

## Publication and bounds

Current rankings require a published series and its latest published modern
run. Archived rows never qualify merely because they retain a timestamp.
Legacy null-run rows are retained only without a modern published run, with
published status or a null status plus a publication timestamp. Public ranking
eligibility is also applied.

Entries use database pagination of 25. Other dashboard history cards are labelled
as the latest 20. The selector reads the first 100 linked profiles and retains an
explicitly selected authorised player outside that set; Manage players pages can
open every linked profile. Automatic selection scans at most 25 linked players.
Upcoming matches inspect the earliest 500 published scheduled candidates per
type before canonical participation/visibility filtering, with a visible notice
when that limit is reached. Counts then describe the matches shown. Canonical
schedule publication validation still reads event-wide context as needed; this
limit is not a global query or event-size guarantee.

## Loading presentation

My Tennis shows the requested view first, then starts up to five authorized
tab requests in parallel. A tab click joins an in-flight request or uses its
short-lived page copy. One slow tab should not delay the start of another.

Use the shared CapeLoading indicator factory and layout loading styles for new
loading states. Native, Bootstrap and SweetAlert indicators share ring styling;
filled action buttons retain contrasting white indicators. Programmatic heading
focus does not show a keyboard outline, while actionable keyboard focus remains
visible. Preserve cancellation, timeouts, retries and focus restoration.
My Tennis player switching uses the same modal before the GET navigation, keeps
the selected input enabled, and clears the handle on Back/Forward restoration,
timeout or submission failure. Native and Select2 changes use one handler.

## Regression pointers

- `tests/Feature/Authorization/UserPlayerLinkAuthorizationTest.php`
- `tests/Feature/Authorization/PlayerRelationshipPrivacyTest.php`
- `tests/Feature/Authorization/PlayerPrivateResultArchiveTest.php`
- `tests/Feature/PlayerIdentityDeduplicationTest.php`
- `tests/Feature/ExternalTeamRosterWorkflowTest.php`
- `tests/Feature/MyTennisDashboardIntegrationTest.php`
- `tests/Feature/MyTennisRatingSnapshotTest.php`
- `tests/Unit/MyTennisServiceTest.php`
- `tests/Unit/PlayerRatingSnapshotTest.php`
- `tests/Frontend/site-loading.test.cjs`
- `tests/Frontend/admin-loading.test.cjs`
- `tests/Frontend/my-tennis-player-switch.test.cjs`
- `tests/Frontend/my-tennis-workspace.test.cjs`
- `tests/Feature/PlayerWorkspaceHeaderTest.php`
- `tests/Feature/PlayerWorkspaceContextTest.php`
- `tests/Feature/PlayerRatingHeaderPresentationTest.php`
