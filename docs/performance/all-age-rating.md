# Cape Tennis all-age singles rating

The shared rating is a private, provisional 0–100 measure. One player has one
shared score across age categories. Age and gender filters change membership of
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
results older than that are excluded. Future results, duplicated sources, doubles, finishing-order
inferences and age-specific trial anchors do not enter this fit.

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

The current `AllAgeRatingModel::VERSION = 6` accepts otherwise eligible played
singles across ball and court formats. Its `format()` does not exclude matches
or infer standard format from age, category, missing metadata or court labels.
These labels do not add strength offsets. Identity, publication, completed-match,
date and duplicate-source checks still apply. Red and Orange use their separate
ball-level projections; this does not make their scores interchangeable.

Earlier policies excluded some modified or unresolved formats. Historical
validation results from those policies are not evidence that version 6 is
calibrated to competition levels. `AllAgeRatingModelTest` covers the current
format behavior. Confidence describes evidence sufficiency, not accuracy or
Wilson/TSA readiness.

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

## Integration and saved data

The additive `all_age` snapshot retains legacy `cohorts` for explicit historical
review. Combined-model profiles, event membership lists, authorized badges,
the integration API and default monthly history use the shared projection.
Disconnected or missing estimates never fall back silently to an age-relative
score. Directory presentation follows the distinct ball-level policy below.

The Super Admin directory's ordinary Overall choice is an all-age view of the
Green/Yellow CTR (`standard`) graph, not this combined model. It displays the
same scores as its CTR choice and clears age restrictions. Explicit historical
Overall comparison-group links remain a labelled review of this combined graph.
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
