# Explicit playoff lucky-loser replacement

After the round robin, an event administrator may click an unstarted playoff
player's name, choose **Replace with lucky loser**, search the event's active
entrants and explicitly confirm withdrawal from that playoff place. A standalone
**Playoff lucky loser** form remains available from the draw header.

The candidate may come from any division in the same event. Show the source
division; do not silently narrow the search to the target category, age or gender.
The source must remain an active paid/free canonical event entry with exactly one
player, and the player must pass normal event eligibility checks. The user's
explicit decision is to **block a player while they have another active playoff
place**. Completed previous matches do not by themselves block selection.

`PlayoffLuckyLoserService` creates a draw-only registration alias linked to the
existing source registration and entry. The alias displays `(LL)` beside the
replacement's name. This decorates the alias's cloned player representation;
the shared player profile and original registration name stay unchanged.
The service does not create an event entry, transfer payment, change account
ownership, remove group membership or rewrite round-robin results.

The explicit action itself records competition-only withdrawal from the selected
playoff place. A prior financial registration withdrawal or round-robin void is
not required. Its audit retains original and replacement identities, exact
fixture/slot and source entry. The outgoing player must have participated in this
draw's round robin. Started/scored/automatic/carried playoff paths, other outgoing
playoff places, duplicate active candidate commitments and schedule conflicts
reject the operation rather than reconstructing an unsafe branch.

The reviewed revision covers result/rule/participant state, event source entry
identity, private bookings and published assignments. Event, draw, fixture,
source entry and player locks serialize application. Repeat submission of the
same applied selection returns the existing alias without another audit.

Exact fixture-slot overrides are stored in the draw's `team_draw_selection`
metadata. Both normal round-robin playoff resolution and Interpro resolution
honor the override while validating its original source. New winner/loser feeds
then carry the alias normally. Interpro repeated-pair detection compares physical
player identities so a new alias cannot bypass first-result-stands. Original
source winners and score orientation are mapped to current fixture identities;
carried results do not create new played set records. An immediate replacement
pairing with a recorded previous meeting requires separate review and is rejected.

Live player results validate the alias against its active same-event source.
Saved rating/history admission keeps its existing exact-category roster check;
this feature does not invent target-category membership or broaden CTR evidence
confidence. Such alias evidence remains unavailable there pending a separately
reviewed mapping.

Owning sources: `PlayoffLuckyLoserService`, `PlayoffLuckyLoserController`,
`RoundRobinPlayoffScheduleService`, `InterproRoundRobinFormat`,
`IndividualMatchOutcomeService`, `Registration`, and the shared bracket/player
renderers. Regressions: `PlayoffLuckyLoserTest`, `InterproRoundRobinFormatTest`,
`AlternateReplacementTest`, and `MyTennisLiveResultsTest`.

This is distinct from `AlternateReplacementService`, whose pre-first-point
replacement updates the original draw/group positions. Do not reuse that service
for a post-round-robin replacement. This feature needs no migration; local
verification does not authorize deployment or a live replacement.
