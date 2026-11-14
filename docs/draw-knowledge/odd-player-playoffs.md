# Optional playoff for the last three players

`OddPlayerPlayoffService` supports one unlocked, single-group individual
round robin with an odd field of at least three and consecutive position-pair
playoffs. In Settings, an explicit confirmation can add a preliminary between
the final two group positions. Its winner plays the third-last group position.
The preliminary loser receives the last position; the final decides the next
two positions. This is opt-in, not an automatic change to existing formats.

The option requires stable, unqualified and unplayed pair placeholders. It
preserves the existing last-pair final ID and every saved booking, converts that
final to round two, and adds exactly one unscheduled feeder. The user must
schedule that preliminary before the existing final; the option never shifts
an existing time. Other fixtures and all scores remain unchanged. Once players
have qualified or play has started in the tail, changing its topology requires
a separate reviewed workflow.

The review/apply boundary checks event scope, progression authorization, current
configuration and a snapshot including membership, fixtures, results and all
three booking stores. Apply locks the draw and fixtures, audits the change and
is idempotent. Generic settings retain the marker but cannot create or remove
the special topology. No migration or score replay is involved.

`RoundRobinPlayoffScheduleService` resolves the original pair fixtures and new
feeder from canonical completed outcomes, including scoreless walkovers and
timed draws. A saved partial set is not a completed group. The final's empty
slot awaits the preliminary winner and must never be advanced as a bye.

`DrawFinalPlacementService` projects all three tail positions as their matches
finish. For an ordinary odd-sized pair draw with no opt-in, the unpaired last
group player appears only after complete, unambiguous group results.
Unresolved group-position sources render as awaiting qualification, not BYE.
`DynamicBracketEngine` renders exactly one preliminary and one final for the
three-player section, without phantom matches or positions.
The final's top player line starts at the preliminary's midpoint, so its
winner connector joins that slot directly. The direct entrant occupies the
bottom line; the final retains the normal match height.

Regression: `tests/Feature/Draw/OddPlayerPlayoffTest.php` covers preservation,
authorization, event isolation, stale snapshots, idempotency, actual progression,
winner advancement, public finishing positions, settings round trips and
canonical completion. Related regressions are
`RoundRobinPlayoffSchedulingTest`, `CanonicalBracketPresentationTest`,
`AutomaticPlayoffPreparationTest` and `AllPlayersPlayoffGenerationTest`.

Local verification does not establish deployment or authorization for a live
draw. Current operational approval and a fresh live review are always required.

### Unscheduled prerequisites in venue scoring

The working venue scoring page shows organizers a separate unscheduled
prerequisite section for unplayed, unbooked winner or loser feeders of the
visible booked matches. Lookup stays within each anchor's event and draw,
walks at most ten levels and one hundred candidates, and excludes completed
or booked ancestors. Published views and dedicated event scorekeepers do not
receive these cards. Normal queue counts and next-on-court choices stay intact.

These cards reuse the normal score modal without assigning a time or court.
Invalid timed defaults show a settings notice instead of inventing timing or
preventing the booked queue from loading. Saving a prerequisite advances its
existing parent slot through the normal scoring service; it does not move
the parent booking. Regression:
`tests/Feature/Draw/UnscheduledScoringPrerequisitesTest.php`.
