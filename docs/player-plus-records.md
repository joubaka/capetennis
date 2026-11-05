# Player Plus records

Playing Record and Opponent Insights are private My Tennis views of existing
saved match evidence. `MyTennisController::playingRecord()` enforces
`PlayerRatingAccess` before reading results; purchasing Plus or sponsoring an
entry does not establish a player relationship.

See [dashboard access and integration](player-dashboard-access.md) for reviewed
new-link rules, retained historical relationships, shared navigation and
standard-versus-Plus visibility.

## Evidence scope

`PlayerRatingHistoryService::recordSource()` applies the existing saved-history
publication, source-change and TeamSheet context guards. The saved evidence is
not a complete career archive. Missing records do not establish that a player
has no other results or event documents.

`PlayerPlusRecordService` selects only matches involving the requested player,
excludes future and unused evidence and finishing-order rows, and deduplicates
rating projections by source ID. Conflicting duplicates are withheld. Ordinary
users cannot see restricted TeamSheet match details. Opponent links show only
the selected player's eligible meetings, without granting access to the
opponent's private profile.

Dates retain their source basis, including schedule/event-date estimates.
The Results month chart counts the full authorized match set before pagination,
using the selected calendar year or the 12 months ending at the saved archive
date. A month link filters only the match register; season totals, recent form
and season comparison retain their existing scopes. An empty month describes
the saved archive, not whether the player played any matches.
Earliest-match and earliest-win markers refer only to available evidence.
Season totals, recent form and head-to-head counts describe recorded outcomes;
they do not estimate strength or change CTR. Results, opponent lists and
meetings are paginated. Page reads do not calculate ratings or mutate results.

## Season comparison and opponent trends

Season check compares two calendar years from the same eligible archive,
independently of the match-register year filter. It reports matches, wins,
losses, win rate, event count and distinct opponents. Change means the
comparison year minus the starting year; rate changes use percentage points,
calculated before rounding. Identical or unavailable seasons have no delta.
Recorded date ranges describe the available evidence, not full-year coverage.

Selecting an opponent opens a matchup above the list and anchors navigation
and meeting pagination to it. Across seasons groups that opponent's eligible
meetings by calendar year within the active year filter. A single paginated
match register shows the scores, without a duplicate recent-meetings list.
Sparse records are labelled.
Estimated dates and matches sharing a date do not establish exact playing
order, so these views do not infer streaks or playing-strength improvement.

## Verification

`tests/Feature/PlayerPlusRecordTest.php` covers the projection, deduplication,
filters, comparison arithmetic and empty states, opponent trends, pagination,
access checks, publication guards and escaped output.
Retain those boundaries when extending the views or changing the source.
