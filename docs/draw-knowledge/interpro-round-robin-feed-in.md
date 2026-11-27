# Interpro round robin and fixed feed-in

The explicit **Interpro trials — round robin → feed-in** setup choice uses the
shared individual draw workspace. It stores `round_robin_playoffs` with preset
`interpro_rr_feed_in`; it does not convert existing draws automatically.

There must be four groups named A–D, each containing three or four players.
Complete the full group programme and resolve tied finishing positions before
reviewing and confirming all four groups. Fourth-place finishers have no playoffs.

## Append challenge matches without resetting a published draw

Before qualifying groups are confirmed, an event administrator may use
**Settings → Interpro challenge matches** to append selected pairs 2 v 3,
4 v 5, 6 v 7, 8 v 9 or 10 v 11. Existing selections cannot be removed here.
This is a separate append-only action; changing the format through Setup still
requires the existing destructive-reset safeguards.

`InterproChallengeSelectionService` locks the event, draw and fixtures, verifies
the existing exact template and qualifying routes, rejects duplicate match
numbers and occupied terminal routes, and checks the reviewed revision. It
adds only missing challenge fixtures and their terminal feeder links. Existing
fixture IDs, participants, scores, bookings and publication remain intact.
New challenge fixtures have no booking or published schedule assignment; review,
schedule and publish those separately. All base playoff matches must finish
before challenge participants resolve. Repeating an already applied selection
is a no-op with no duplicate fixtures or audit record.

Regression: `tests/Feature/Draw/InterproChallengeSelectionTest.php`. The action
does not support removing challenges or adding them after qualification has
been confirmed. Local implementation and this guide do not authorize release
or a live structural change.

## Conditional daily match limits

`DailyMatchLimit` uses `InterproDailyMatchGraph` to check the canonical feed-in
routes before qualifiers are known. Group members are possible entrants until
qualification resolves; winner/loser alternatives count the maximum appearances
on a feasible route rather than adding mutually exclusive matches together.
Round-robin appearances and other registrations for the same player profile
still count toward the event-wide daily total. Canonical carried results and
automatic advances add no extra appearance. Malformed qualifier sources,
foreign participants and stale carried references fail closed.

Scheduling also uses `InterproParticipantGroups` to prove that sibling playoff
branches contain different entrants. The exemption requires the exact canonical
routes, valid group sources, complete unique profile memberships and either no
qualifiers or all twelve distinct qualifiers. Only proven same-draw playoff
branches share the group; RR appearances, physical court occupancy, cross-draw
player collisions and prerequisite completion plus rest remain enforced. Apply
the same proof to new nodes, saved bookings and save-time revalidation.

Select resolved opening qualifier models by their `match_nr` field. Eloquent
collection `only()` selects model primary keys even after `keyBy('match_nr')`,
so it cannot identify the twelve opening places by their match numbers.

Regression: `tests/Feature/Draw/InterproDailyMatchLimitTest.php` and the existing
`DailyMatchLimitTest` unit cases. This validation does not create bookings or
publish times, and unsupported unresolved formats retain their existing guard.

## Best of three starting at 2–2

For two full sets from 2–2 followed by a deciding match tiebreak, select
**Two full sets from 2–2 · deciding 10-point tiebreak** separately for
**Round-robin scoring** and **Playoff scoring**. The latter covers every
championship, lower-bracket, placement and optional challenge fixture.
The key is `best_of_3_start_2_match_tiebreak`. Enter actual third-set points:
10–8, 11–9 or 12–10 are valid; 10–9 is unfinished. Extended tiebreaks must finish
with exactly a two-point margin. The first two sets include their starting games.
In round-robin standings the completed decider counts as one set and one game,
while its actual points remain visible.

Already-scored Interpro draws use the separate reviewed scoring-correction
workflow, not an ordinary settings override. It checks every existing result,
requires explicit actual deciding points where incompatible, retains the first
two set rows, checks stale state and winner changes, and protects confirmed
qualification, downstream play, bookings and publication. Never infer tiebreak
points from a stored 7–6 full-set result. See
`InterproMatchTiebreakCorrectionTest` and `RoundRobinMatchTiebreakStandingsTest`.
An RR fixture with no set rows, no winner and status 0 or 2 is unscored for this
review; it contributes no compatible result and keeps its stored status and
bookings. This exception never ignores recorded partial scores, terminal or
winner-bearing empty outcomes, or a playoff marked partial. Supplying a third-set
correction for an empty fixture is rejected.
This guide establishes local behavior, not deployment or live mutation authority.

Choose **Best of 3 full sets · start at 2–2** separately in **Round-robin scoring**
and **Playoff scoring** when both stages use those tournament rules. The selections
may differ. This stores `best_of_3_full_start_2` in `score_format` and
`playoff_score_format` through the authorized competition-settings save.
The nullable playoff column requires the explicitly allowlisted migration
`2026_11_27_140000_add_playoff_score_format_to_draw_settings.php`.
Existing draws are not converted automatically. Save both explicit choices before
the first result. Scored or confirmed sources retain their normal rule-freeze
and recovery restrictions; never bypass them to convert an active draw.

Record final totals including both starting games: `6–2, 6–3` or
`6–2, 3–6, 7–6`. A player must win two completed sets. Partial or drawn sets,
missing deciding sets, totals below two and sets after a straight-sets win are
rejected. Normal full sets end 6–2 through 6–4, 7–5 or 7–6 in either orientation.
This preset also remains decisive in an ordinary round robin. Other ordinary
historical RR formats retain their documented draw support. Selected Interpro
feed-in draws require decisive non-timed preset results because every original
match must resolve before confirmation; legacy blank settings are not silently
converted into this new scoring format.

Use the separate retirement or walkover result when appropriate. A retirement
may retain an unfinished 2–2, 3–2 or 6–6 score, but only its final set may be
unfinished and a completed match cannot become a retirement. A walkover never
creates set scores. Games percentages use recorded final totals, including the
starting games. The [round-robin tie policy](round-robin-ties.md) is documented
separately from score validation.

Fixture stage determines validation, score-entry guidance, outcome and timing:
RR uses `score_format`; other stages use `playoff_score_format`. A missing playoff
choice falls back to the old format for historical compatibility, but setup warns
before play and the two-stage settings form requires both choices. This warning
does not automatically unpublish or block publication of existing legacy draws.
Scoring-rule revisions include both fields, so a previously opened score form
must reload after either changes. A playoff-only settings save preserves RR rules.

Regression sources: `StartAtTwoScoringTest`, `ScoreValidationServiceTest`, and
`tests/Frontend/set-score-grid.test.cjs`. Local opt-in and testing do not authorize
deployment, changing live scoring settings, rescheduling or publication.

## Fixed routes

- A1–D1 and B1–C1 are championship semifinals; their winners contest 1st/2nd.
- A2–D3 and B2–C3 produce one crossover survivor; C2–B3 and D2–A3 produce another.
- Those survivors meet, respectively, the loser of B1–C1 and the loser of A1–D1.
  Winners contest 3rd/4th; losers contest 5th/6th.
- The two players eliminated immediately before the feed-in contest 7th/8th.
- Initial crossover losers play two semifinals, then 9th/10th and 11th/12th.

The declarative template in `InterproRoundRobinFormat::template()` is the owning
topology. Its 18 playoff identities remain stable after preparation, including
before qualifiers are known. A full 16-player field has 24 RR matches and up to
18 playoff matches; carried results reduce the matches actually played.

## Optional adjacent-position challenges

Before results are recorded, draw setup can enable selected base-position
challenges: 2 v 3, 4 v 5, 6 v 7, 8 v 9 and 10 v 11. The default remains off.
The normal setup authorization, publication, result-freeze and explicit-reset
guards apply; this option is not a post-result draw conversion.

Selected challenges are stable `CHALLENGE` fixtures numbered 5000 plus the
higher base position. All eighteen base matches must resolve before challenge
participants qualify, and scheduling uses the same full-base dependency barrier.
Pairs use the fixed base positions, never positions changed by another challenge.
The winner takes the higher position. A pairing already played in this exact
playoff bracket skips its challenge and retains both base positions, without
copied scores, a synthetic played match or new rating evidence. Skipped fixtures cannot be
scored, booked or converted into withdrawal walkovers. Working bookings are
released with audit history; immutable published snapshots remain intact.

Pending challenges withhold their two final positions and the ordered category
results prefill until all positions are decided. Admin, public and print brackets
use the shared challenge board and canonical schedule publication projection.
`InterproRoundRobinFormatTest` covers setup persistence, fixed sources, repeat
preparation, pending results, upset positions, skipped-pair evidence counts,
scheduling exclusions and preserved withdrawal history.

## First result stands

The first decisive playoff meeting in this exact draw determines a repeated
playoff pairing. Round-robin results never carry into the playoff bracket and
do not block a lucky-loser replacement. The earlier playoff topology source
takes precedence. Existing stored round-robin carry references and RR-only
skipped challenges require review; they are rejected without rewriting history.
A drawn first playoff meeting cannot silently become a win.

A carry is a progression reference to a genuine original result, never a second
played match, copied set rows or synthetic walkover. Store the source ID and
content fingerprint inside the selected preset's configuration. Canonical
outcomes verify draw identity, unordered participants, earlier topology and the
original fingerprint. Display scores in the current slot orientation and label
the original match. A changed or unavailable source requires review.

Confirming progression requires a decisive result in every RR match, including
when timed scoring is selected. Resolve drawn original matches during the group
stage before confirmation so group qualification remains decisive.

Resolve both winner and loser paths in template order, independent of the order
in which scores arrive. Carried pairings cannot be scored again or booked on a
court. Release their working booking when they resolve, retaining the audit and
immutable published snapshot; public schedule projection omits the obsolete
booking. No carried fixture set rows means no duplicate played-match or rating
evidence.

Withdrawal reconciliation retains completed and carried history. A future real
match follows the canonical withdrawal loss; a branch with no available player
passes an explicit absence forward, allowing its next opponent to advance
without inventing a played match. Generic bye inference must not interpret a
pending feed-in source as an absent opponent.

## Current correction boundary

Confirmed RR results, completed playoff results and carried targets/originals
reject direct score replacement or clearing. The existing RR tournament-recovery
operation also rejects changes to confirmed trial sources: it does not yet reset
and reconstruct this special graph safely. This is an explicit limitation,
not an instruction to bypass guards or edit records directly. A future correction
workflow must review all feeder and carry dependencies and preserve publication,
schedule and audit history before changing this boundary.

Use the shared event scheduler; legacy plate generation and older timetable
writers cannot replace this format. Setup changes retain normal authorization,
event isolation, unpublication, result protection and explicit-reset safeguards.

## Verification sources

- `tests/Feature/Draw/InterproRoundRobinFormatTest.php` covers historical field
  sizes, stable topology, reviewed progression, carried outcomes/evidence counts,
  all 12 positions, schedule exclusion, score protection, publication and
  withdrawal handling.
- Adjacent regressions: `RoundRobinProgressTest`,
  `AutomaticPlayoffPreparationTest`, `InterproDrawGenerationPreservationTest`.

These are source/test references, not production deployment or browser evidence.

## Fictional review snapshots

Set `INTERPRO_PREVIEW_EXPORT=1` when running the focused
`test_first_results_cascade_without_duplicate_evidence_and_determine_all_twelve_positions`
regression to export a static walkthrough under
`storage/app/testing/interpro-preview/index.html`. The exporter requires
`APP_ENV=testing` and the exact `ct_testing` database. Its fictional records roll
back with the test; snapshots have disabled controls and local asset links.
When the testing URL differs from Wamp's configured URL, set
`INTERPRO_PREVIEW_ASSET_BASE` to the reviewed local asset root before exporting.

The screen and graphical print view use the user's conventional stacked layout:
a four-player championship bracket at the top, followed by the eight-player
lower bracket. The lower rounds have four opening matches, two survivor matches,
two Round 3 feed-in matches and the 3rd/4th final. The championship semifinal
losers flip: upper semifinal 2001 feeds lower Round 3 match 3008, and lower
semifinal 2002 feeds upper Round 3 match 3007. A subordinate board contains the
remaining six placement/consolation matches. The recovered historical sheets
explain the topology, but their mirrored arrangement is superseded by this
explicit layout decision. This never changes stored competition sources.
Each of the eighteen canonical matches and all twenty-four winner/loser
dependencies remain in the read-only projection. Draw only normal bracket stems,
within-section winner connections and terminal winner ending lines. Do not add
arrows, dashed loser routes, cross-section connectors or source stubs. The flipped
championship losers are source labels when unresolved and source tooltips when
the players are known. The six terminal endpoints are not extra fixtures.

Use the actual Flexible Monrad HTML/CSS and shared `TennisBracketLayout` layout
and DOM factories, not a separately styled SVG imitation. Both renderers use
220-pixel matches, 260-pixel columns, 32-pixel slots and 64-pixel line spacing.
Player slots are 13px, match labels 10px and schedule labels 9px. Known players
use content-sized identity chips with source details only in the slot tooltip;
unknown winner/loser entries use the actual 9px feeder chip. The shared surface
explicitly retains normal line height when embedded in another layout.
Carried set columns come only from the validated
original result, reversed into the target slot orientation; invalid references
show review wording without stale numeric scores. Preserve W/O/RET and automatic
advance notices. Full labels remain available in HTML titles and the
expandable source/results table; long labels cannot cross into score columns.

The board keeps actual Monrad native sizing and horizontal scrolling on desktop
and phones. Zoom must enlarge the board,
and the public header controls must resolve the bracket in their enclosing card.
The shared admin workspace exposes zoom controls on desktop as well as phones.
The public inline-block zoom host becomes a block for this preset so introductory
text and final positions wrap within the viewport while the diagrams scroll.
Schedule labels start with `SchedulePublicationService::publishedRows()` for
this exact draw and individual fixture, retaining event/draw/schedule publication
and first-match visibility filtering. Published labels are green and say
**Published**. Only an authenticated `super-user` receives a separately queried,
draw-scoped working overlay from `InterproBracketScheduleProjection`; changed or
unpublished bookings are orange and say **Preview only**. Include day, time,
venue and court; venue identity also matters when comparing snapshots. Ordinary
admins, other users and guests receive published metadata only, regardless of
query flags. Never put working metadata into publication caches. Both public and
backend bracket AJAX responses are private/no-store and vary by Cookie and
Authorization. Empty bracket printing omits all schedules. Public revisions
remain publication-only; reload the bracket to refresh a Super User preview.
Regression: `InterproSchedulePreviewTest` and
`tests/Frontend/interpro-schedule-labels.test.cjs`.
The individual round-robin `frontend.fixtures.index` page reuses the canonical
public draw workspace: **Fixtures**, **Round-robin standings**, and **Playoff
bracket**. Fixtures open first, including unscheduled pairings; private times and
venues remain filtered by the publication service. The results grid is optional
detail under standings. Fixtures visit each group’s round before the next round,
using full group membership for larger-group-first/name ordering. Existing
`#draw` and `#matrix` links open that detail; `#playoffs` opens the bracket.
Both entry URLs retain canonical event/draw visibility checks and navigation
never prepares fixtures or publishes a draft. Public revision checks reload the
whole workspace on changes, preserving the selected tab hash.
The fictional browser harness uses the actual workspace wrappers and stylesheet
order to check readable names, reachable final rounds and zoom/reset behavior.
It also generates a four-player draw through the actual Monrad demo UI using a
locally intercepted pure-compiler response and compares computed typography and
chip styling. The read-only custom element mounts after initial parsing, public
`innerHTML` and print-popup `document.write`. JSON uses HEX escaping and names
are inserted with textContent. A missing or failed component blocks printing.

Show all six finishing bands: 1–2 (2003), 3–4 (3009), 5–6 (3011), 7–8 (3010),
9–10 (4003) and 11–12 (4004), using separate placement headings. Normal views
always list positions 1–12, including an empty planning draw. Fill only missing
rows with honest Awaiting result placeholders; retain canonical names, byes and
outcomes. Blank planning/printing omits the final-position list.

Print Bracket and Print Empty Bracket use the same DOM/vector boards on landscape
pages, with per-board fit constrained by printable width and height. Populated
printing puts final positions on a separate page;
the popup embeds only the trusted shared application CSS/modules and removes
their explicitly marked external asset block, so standalone printing needs no
asset fetch. Rendering readiness still gates the print action.
blank printing omits names, scores, schedules and final positions. The source
table is optional screen detail, not a substitute for the printed bracket.
The print content check recognizes this preset as well as other SVGs. For the
selected preset, a missing or failed
bracket response must ask for a retry rather than fabricate a generic four-player
fallback. Draw-pack playoff fixtures come from canonical non-RR rows, preserving
all eighteen source routes and carried-result labels.
