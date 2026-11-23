# Cape Tennis all-age singles rating

CTR is the private, provisional 0–100 Green/Yellow rating. Red and Orange have
independent ratings and evidence pools; no mixed-ball rating is permitted.
One player has one score per ball pool across age categories. Age and gender filters change membership of
lists, never the player's estimated strength. It is not UTR, WTN, a win
percentage, or an official selection ranking.

The admin ratings directory defaults selected junior age filters to that age
and younger. U9–U13 use calendar birth years: in the 2026 season U11 includes
valid birth dates in 2015 or later, including both January and December births.
The explicit birth-year-group-only option retains exact-year filtering for U9–U13. The
unfiltered form retains the younger-age choice for its next submission. Red and
Orange remain age independent. See `PlayerRatingGroupDirectoryTest` for the
cutoffs, New Year rollover and exact-group override regressions.

## Evidence and comparability

`AllAgeRatingModel` fits the existing regularized Bradley–Terry likelihood to
canonical eligible played singles. It uses the existing bounded score-margin
targets and per-player incident budget of one per event. Match weights have a
180-day half-life. Results within 730 days may establish shared connections;
results older than that are excluded. Future results, duplicated sources, doubles
and age-specific trial anchors do not enter the played-match fit. Separately
validated published finishing fields may provide weaker evidence within their
own ball pool. Finish-only estimates remain local and noncomparable; they never
manufacture played matches or entry into the comparable Top 10.

Shared players and opponents connect age categories. Age, gender and event labels
provide no strength offsets. The largest component connected by matches within
365 days supplies the recent reference backbone; ties resolve by played-match
count and then lowest member ID. Its expanded two-year component supplies the
provisional shared reference. If there are no recent matches, no shared reference
or score is assigned. Other
components have no shared score. Their internal centered scores are not exported
or substituted for the shared rating. A nonconverged or corrupt saved graph is
withheld. Even a connected graph does not prove strong calibration across all its
age boundaries.

For each player, a minimax path calculation identifies the freshest supported
connection to that recent backbone: it minimizes the oldest edge needed along
any available path. A connection requiring a match older than 365 days has
`historical_connection=true`, a recorded bottleneck age/date, and evidence
confidence capped at 39/Low. This cap also applies when a player's own matches
are recent but the group's connection is old. A fresh alternative path removes
the historical flag. Backbone members use age zero as a reference marker, not
as a claim that they played today. Historical links remain provisional because
playing strength, especially junior strength, can change substantially over time.

The display uses the existing fixed `100 × logistic(strength)` transform. The
reference is locally centered, so 50 is not a certified tennis standard. Changes
to the comparison network can move estimates. History marks changed comparison
groups and does not report those changes as like-for-like monthly gains.

## Playing format

The standing product rule keeps Red, Orange and Green/Yellow evidence and scores
separate. CTR and any "Overall" all-age list use Green/Yellow only; "Overall"
never means all ball levels. Explicit Red or Orange contexts use their own saved
graph and never borrow a CTR score, or vice versa. Age and gender filters do not
change the score or admit evidence from another ball pool.

Classification uses `BallRatingModel` and `BallRatingContext`, including the U9
Orange fallback and explicit playing-condition overrides documented in
[ball-level classification](ball-level-classification.md). Identity, publication,
completed-match, date and duplicate-source checks still apply.

Earlier combined-ball calculations are superseded and must not be displayed as
CTR or used as a missing-rating fallback. Saved calculations and derived history
require a policy-compatible rebuild; a code deployment alone does not establish
that the rebuild has completed. Confidence describes evidence sufficiency, not
accuracy or Wilson/TSA readiness.

By explicit product decision, Player Plus shows a personalized Cape Tennis
tournament stage from the saved comparable Green/Yellow CTR: Wilson B at 50 or
below, Wilson A above 50 through 75, and TSA tournaments above 75 (configured in
`config/pathway.php`). These are guidance bands, not externally validated
readiness or official entry/selection rules. A pending nightly update may retain
the labelled latest-saved stage; fresh suggestions remain subject to the saved
assessment policy. Local/noncomparable, missing, mismatched-player and revoked
evidence cannot assign a stage. Pending ball moves suppress destination placement
until qualifying saved evidence is available. Red/Orange progression targets
remain separate. See `PlayerPathwayService` and `PlayerPathwayTest`.

### Tournament stage and upcoming event links

The hero, map and detail panel share an explicit presentation state: current,
latest saved/update pending, insufficient comparable evidence, recorded move
pending, no rating, or unavailable guide configuration. Red/Orange name the rated
ball stage and show its own CTR and published-event targets. Orange CTR never
selects a Green/Yellow division. Both competition cutoffs must be valid and TSA
must be strictly above Wilson A before any of the three bands is shown.

`PlayerPathwayEvents` discovers only published upcoming individual events,
including ongoing events under the canonical date convention. Terminal,
postponed, results-published and Masters/Trials invitation formats are excluded.
Hosted levels can be configured explicitly as an optional event default and an
event-category override. `EventPathwayClassification` validates these labels
against known ball evidence; a conflicting explicit setting never falls back to
a guessed label. These discovery settings do not alter match rating evidence.
When settings are absent, legacy matching uses explicit category ball words or
exact U9/U10 B Orange classification; Wilson needs an explicit Wilson A/B category
or an exact Wilson type with one category division. Generic event titles and
unclassified standard categories cannot manufacture a match. TSA remains an
external circuit, not a hosted classification. Conflicting family or ball
metadata is withheld. Canonical `BallRatingContext` checks event-scoped court
evidence before inclusion. See `../player-plus-pathway.md`. Known exact category age/gender exclusions use
the existing calendar-year convention; missing or ambiguous profile/category
information remains an organiser review, not proof of admission.

Reads are bounded to 50 candidate events, 20 categories per event, 20 canonical
context checks in total and six cards. A cap gives an explicit more-events
notice. Cards contain public event links, date, limited venue names, canonical
entry-open/closed status and matching category labels. Latest-saved stage links
remain visibly update-pending. Missing/insufficient stages and pending moves do
not suggest events. An empty list means no confirmed match in this bounded local
catalogue, not that no suitable tournament exists. Discovery never creates an
entry, grants invitation access or performs registration eligibility mutations.
Focused coverage: `PlayerPathwayEventsTest` and `PlayerPathwayTest`.

## Integration and saved data

The compatibility `all_age` snapshot key represents the Green/Yellow graph only;
it must never carry a combined-ball fit. Red and Orange retain independent
graphs. Profiles, event membership lists, authorized badges, the integration API
and monthly history must respect those same pool boundaries. Disconnected or
missing estimates never borrow another pool or a legacy age-relative score.

The Super Admin directory's ordinary Overall choice is an all-age view of the
Green/Yellow CTR (`standard`) graph. It displays the same scores as its CTR
choice and clears age restrictions. Historical Overall comparison-group links
must match the current Green/Yellow graph or withhold the mismatched rating.
See `ball-level-classification.md` for directory filter and navigation rules.

Model/policy hashes invalidate earlier saved calculations. Existing background
commands rebuild the private current snapshot and monthly history; ordinary page
reads do not calculate ratings. No migration or change to canonical results,
rankings, publication, registration, finance or premium-access configuration is
required. Existing audience, roster and token boundaries apply unchanged.

The current CTR pages and name badges read the last successful database snapshot.
An open page keeps its rendered badges. Dynamically loaded rows make an authorized
saved-rating lookup when they appear. Family views show a Top 10 and the selected
player’s position from the same eligible age and gender group. Super users can
browse the directory and choose a birth-year group or include younger players.

Saved format manifests recapture complete event-court, allocation and schedule
scopes, including empty scopes. Changes invalidate saved estimates. Internal
strength, disconnected local scores and other players' provenance are not
included in authorized badge projections.

## Validation

`php scripts/validate-player-ability.php --event=ID --all-age` performs a read-only
chronological holdout using only events completed before the held-out start. It
reports coverage, Brier score, log loss, a chance reference on identical pairs,
original-age category subgroups and exclusions. `--compare` age-specific anchor
experiments cannot be combined with `--all-age`.

Add `--compare-history` with `--all-age` to compare the two-year policy against a
365-day fit using the same held-out event. Reports distinguish coverage gains,
historical/recent connection subgroups and paired Brier/log-loss changes on
identical eligible matches. More connected players do not by themselves establish
better predictive accuracy.

These are retrospective checks using current publication/identity records and
date proxies. Sparse or selected pairs cannot establish universal calibration.
Missing connections require suitable recorded matches; neither category labels
nor historical finishes establish cross-age equivalence.

## Connecting remaining groups

The private ratings directory includes a connection plan derived from the saved
graph. It lists separate groups, the minimum number of links required to join
them, and nearby recorded age categories to investigate. The plan prefers
same-gender age links before cross-gender links, but independent gender networks
still need actual connecting singles evidence to share a reference.

Suggestions are a deterministic topology plan, never new fixtures, results,
strength equivalences or selection decisions. At most 25 priorities are displayed;
only the largest 100 existing groups are candidate hubs. A single crossing match
can connect a graph mathematically without establishing robust calibration.
Use multiple suitable opponents and repeated events to assess connections.
Review existing source records first. Unverified identity, unknown ball format,
unpublished results and stale matches cannot be converted into connections by
accepting a suggestion or changing an age label.

Verified team singles can contribute to the shared fit even when the two sides
have different age/gender categories or unclassified demographic labels. This
uses an independent eligibility reason captured before the legacy same-cohort
filter: the old demographic error can mask other failures and is never waived
on its own. Both event-side sources, participants, rosters, discipline, completed
scores, dates and publication must still validate. Cross-context or unclassified
matches additionally require explicit standard/yellow playing conditions, and
modified-format declarations on either source remain excluded. Legacy
age-specific estimates keep their original cohort restrictions.
