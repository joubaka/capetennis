# Round-robin retirement and void results

An ordinary retirement remains a match result. After recording a retirement,
an event administrator may explicitly confirm **Withdraw player and void
matches** in the draw workspace or scoring workspace. Confirmation withdraws
the retired player from that exact round-robin group only.

For a round-robin retirement before any point is played, leave all scores empty.
The saved result is RET with no set rows, not an invented 0–0 or starting-game
score. Existing played scores must be retained; a blank retirement cannot erase
them. Ordinary played results and non-round-robin retirements still require scores.

`RoundRobinWithdrawalService` marks all that player's matches in the group
with `fixtures.round_robin_voided_registration_id`. Original match status,
winner and set rows remain history. Existing void markers are preserved on
shared matches when another player subsequently withdraws. Voided matches
display a red strike through the retained score and a **Void** label; they
contribute no match points, wins, losses, sets, games, head-to-head evidence,
CTR evidence or live player result. The withdrawn entrant is excluded from
that group's ranked standings and qualification. Other groups and later
round-robin stages remain independent.

This is a competition operation, not registration withdrawal or a refund.
It does not change entries, payer ownership or money. Locked/team draws,
missing confirmation, non-retired matches, foreign fixtures, unauthorized
actors and stale group revisions are rejected. Repeated confirmations are
idempotent. Ordinary score writers and clear-result routes preserve void
history. Pending result notifications are invalidated without sending mail.

Confirmed source-stage progression and populated playoffs block retrospective
source changes. For prepared second-stage or hybrid placeholders, reconciliation
removes only now-unsupported empty fixtures, with an audit snapshot of removed
private bookings; unaffected fixture identities and bookings remain intact.
Published affected schedules or occupied/played placeholders reject the entire
operation atomically. A void match is resolved for programme completion while
remaining excluded from standings.

Owning sources: `RoundRobinWithdrawalService`, `Fixture`, `StandingsService`,
`InterproDrawBuilder`, `TwoStageRoundRobinService`, `PendingHybridRoundRobinService`,
`RoundRobinGroupResultService`, `IndividualMatchOutcomeService` and the public/
admin round-robin renderers.

Regressions: `RoundRobinWithdrawalTest`, `VoidedRoundRobinProgressionTest`,
`MyTennisLiveResultsTest`, and the exact migration allowlist assertion in
`DeploymentConfigTest`. Migration:
`database/migrations/2026_11_27_160000_add_round_robin_void_marker_to_fixtures.php`.
Local tests and compiled views do not establish deployment or browser visual QA.
