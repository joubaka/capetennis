# Cape Tennis all-age singles rating

The shared rating is a private, provisional 0–100 measure. One player has one
shared score across age categories. Age and gender filters change membership of
lists, never the player's estimated strength. It is not UTR, WTN, a win
percentage, or an official selection ranking.

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

Explicit red, orange, green, mini or short-court formats are excluded. The actual
scheduled event court is preferred; otherwise a consistent draw allocation can
establish its ball type. Mixed or unresolved allocations are excluded. Unknown
court assignment in an event with modified-ball courts is excluded.

U10-and-younger categories require explicit standard/yellow format evidence.
Conventional U11+ legacy category labels may assume standard format when metadata
is absent. A player's age is not used to infer the format of a match. Assumed
matches are counted and identified in the UI, and evidence confidence is capped
at Medium. Confidence measures evidence sufficiency, not calibrated accuracy.

## Integration and saved data

The additive `all_age` snapshot retains legacy `cohorts` for explicit historical
review. Profiles, current age filters, event membership lists, authorized badges,
the integration API and default monthly history use the shared projection.
Disconnected or missing estimates never fall back silently to an age-relative
score. The site-wide directory displays coverage and keeps unrated profiles.

Model/policy hashes invalidate earlier saved calculations. Existing background
commands rebuild the private current snapshot and monthly history; ordinary page
reads do not calculate ratings. No migration or change to canonical results,
rankings, publication, registration, finance or premium-access configuration is
required. Existing audience, roster and token boundaries apply unchanged.

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
