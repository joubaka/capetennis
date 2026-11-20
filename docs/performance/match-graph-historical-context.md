# Match graph historical-input validation

Version 6 private match-graph artifacts capture a raw historical-input signature
offline, alongside the existing exact global build fingerprint. This prevents an
unrelated future signup from discarding a valid saved historical graph. It does
not refresh or calculate ratings during a page request.

`PlayerMatchHistoricalContext` dynamically roots its scan in **all events starting
on or before the artifact date**, including unpublished and empty events. The
scope deliberately exceeds the selected player's latest five matches: historical
network corrections, newly entered scores, new backdated events, publication and
previously empty candidate groups must be detected.

The ordered raw hash includes event publication/date/type, category definitions,
draws/settings, individual and team fixtures and score rows, schedules and court
configuration, event memberships and referenced registrations, team ties,
event-team rosters and same-region/year legacy team candidates, imported and
captured identities, identity recoveries and their canonical dependency context,
and referenced players' names, birth dates and genders. Payment/contact fields
are not exposed in the artifact: only the context version, date and hash are
stored. Category, event-type and region definition tables remain conservative
global dependencies. The external TeamSheet fingerprint is also conservative
and global because its current API has no equivalent historical subset guard.
Consequently an unrelated definition or external-source change may still
invalidate the graph. This is a deliberate fail-closed boundary.

Capture is limited to 250,000 raw rows and 32 MiB of encoded input, including
recovery context. Missing required tables, malformed data or exceeded limits
prevent a usable context. Queries are capped and ordered; this is raw input
validation, never an evidence reconstruction or model fit. The canonical recovery
context also retains its own per-source bounds.

The offline builder captures context before reconstruction and compares it again
at the end. Its original exact global fingerprint checks remain unchanged. The
reader requires the existing actor access, validated snapshot publication and
identity, same SAST day, exact snapshot version, current score and policy. For v6
it then compares the dynamic historical context; failure withholds the graph.
Snapshot publication manifests alone cannot replace this hash because they do
not capture all raw historical score corrections.

Version 5 remains readable under its exact original policy hash and global source
fingerprint equality. There is no retrofit, retag or new context capture onto an
old artifact. Only an explicitly authorized offline refresh can produce v6.
No production build, artifact replacement or deployment follows from local code
validation.

Regression coverage: `PlayerMatchHistoricalContextTest`, `PlayerMatchProgressTest`
and `MyTennisRatingNavigationTest`. Tests use isolated SQLite and private fictional
files, including actual v6 file validation and identical Profile/Rating available
and withheld states.
