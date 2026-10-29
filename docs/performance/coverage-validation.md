# Group coverage and retrospective validation

The private ratings directory supports an explicit `comparison_group` selection.
For a disconnected group it shows `within_group_score`, labelled within-group
only. The saved shared `score` remains null and `comparable` remains false.
Normal profiles, badges, integration responses and global ordering do not fall
back to these local values. Select a group through the connection plan; age or
gender filters alone never enable local values.

Group views recompute connection age against their own recent backbone. A link
older than one year, or the absence of a recent backbone, means Low confidence.
A numeric match between scores in different groups does not establish equal
ability. Reading a group does not create results, link identities or publish data.

## Read-only historical experiments

Run against a local loopback MySQL database:

```powershell
php scripts/validate-all-age-ratings.php --as-of=2026-10-29 --limit=20 --output=C:/wamp64/www/ct/storage/framework/testing/all-age-expanded-validation-2026-10-29.json
```

Use `--events=ID,ID` to select up to twenty explicit holdouts. By default the tool
selects chronological, evenly spaced events with canonical eligible singles,
without examining predictive performance. It extracts source records once in a
read-only repeatable-read transaction. Each holdout uses only events completed
strictly before its start, with predictions frozen before any holdout results.
Overlapping events and the held-out event are excluded from training.

The report separates shared-reference predictions from predictions within each
connected component, so girls' disconnected groups can be evaluated without
pretending they share a calibrated global scale. Gender summaries describe
recorded match categories rather than verified player demographics.

It compares the current two-year model with the one-year window on identical
evaluable pairs. It also compares half/double evidence weight and outcome-only
targets using the same graph and held-out pairs. Coverage gains are reported
separately from probability error. Brier and log losses are lower-is-better;
50/50 predictions have Brier loss 0.25. Winner accuracy excludes rating ties.
Confidence strata in this experimental report use frozen global model labels;
they do not validate the group view's separately recalculated confidence labels.

These are retrospective checks using current corrected scores, identities and
publication records. Match observations share players and events; counts are
not independent samples. No experimental policy is automatically promoted, and
no snapshots, canonical results or configuration are changed. Code hashes reject
reports if calculation source files change during the run. Keep later untouched
events for confirmation before adopting a tuned policy.

## Local evaluation on 29 October 2026

The run selected eighteen eligible events from 2023 through 2026. Sixteen had
compatible pre-event pairs; the first two lacked compatible training evidence.
Within-component scope covered 2,056 held-out matches, including 946 girls'
matches. These counts describe evaluation scope, not pooled accuracy.

Double evidence weight reduced Brier loss in all sixteen evaluable events and
in all fifteen events with evaluable girls' matches. Winner accuracy was mixed.
Outcome-only targets reduced Brier loss in fifteen of sixteen events overall,
and thirteen of fifteen girls' subgroups. These are exploratory results, not
approval to alter normal rating weights.

At the current date the two-year graph contains 765 players, including 362 in
girls' categories. Its largest girls' group has 339 players, of whom 255 have
evidence within the last year. All 268 girls with recent eligible evidence have
within-group estimates, but no verified connection to the global boys' reference
was found. The global reference includes 295 recently active players versus 280
with the one-year window. Older-only participants are counted separately in the
raw report and older connections retain their Low confidence treatment.

Aggregate reports are local artifacts in `storage/framework/testing/`; regenerate
them when source data or policy changes. Do not infer production state from this
local evaluation.

## Reduced shrinkage adopted locally on 29 October 2026

The user authorized applying the tested reduction locally. All-age model version
3 uses regularization 0.25, previously 0.5. This is equivalent to the earlier
double-evidence experiment, while retaining the existing match weights, event
budgets, recency, margin targets, topology and confidence rules. Legacy
age-specific fits keep their default 0.5 regularization and existing anchors.

The all-age coefficient is recorded in snapshot rules and saved graph policy.
The changed policy hash invalidates both current and monthly-history snapshots;
they must be rebuilt locally rather than mixing old and new estimates. Historical
experiments now include `previous_shrinkage` explicitly at 0.5 alongside the
active current 0.25 policy. Further half/double-evidence variants are relative to
the active policy and are not automatically adopted. Local adoption does not
authorize or establish a production deployment.

The post-change rerun is saved locally as
`storage/framework/testing/reduced-shrinkage-validation-2026-10-29.json`.
It retained the same eighteen selected events and 2,056 compatible predictions,
including 946 girls' matches. Against its explicit 0.5 baseline, active 0.25
reduced Brier loss in all sixteen measurable events and all fifteen measurable
girls' subgroups. This reruns the same historical sample; it is not independent
confirmation on new events. Winner accuracy remains mixed: Overberg Leg 3 girls
improved from 15/22 to 16/22 correct favourites, while Platteland 2026 girls fell
from 40/72 to 39/72 despite lower Brier loss (0.2494 to 0.2455).

The local current snapshot was verified current under the new policy, and the
saved history passed policy validation with twelve shared months, current source
fingerprint and a playing-format publication manifest. Focused verification
passed 27 unit tests / 147 assertions and 12 isolated SQLite feature tests /
103 assertions. No canonical match records were rewritten.

## Shrinkage 1/64 adopted locally on 29 October 2026

All-age model version 4 uses regularization 0.015625 (1/64), replacing 0.25.
The current incident event budget remains one unit per player before decay;
neither square-root weights nor a three-unit soft cap were adopted. Legacy
age-specific regularization remains 0.5. The model version and coefficient in
saved policy invalidate both current ratings and monthly histories for rebuild.

The controlled sweep selected this setting using pre-2026 girls' events and a
prespecified simplicity tolerance. The slightly lower error from square-root
weighting did not justify changing both mechanisms. Historical 2026 comparisons
were already explored; they are retrospective checks, not untouched future
validation. The explicit `previous_shrinkage` comparison now means 0.25.

The validation tool retains valid active/previous comparisons when an optional
more aggressive candidate fails convergence. Such a candidate is reported as
`status: withheld`, `reason: candidate_fit_did_not_converge`, with no partial
metrics. Nonconvergence of the active or recent-window baseline still withholds
the report. Local adoption does not establish deployment or alter canonical
results, identities, publication or connected-group boundaries.

The applied-policy verification is saved locally in
`storage/framework/testing/one-sixty-four-validation-2026-10-29.json`. It exactly
matches the selected controlled-sweep candidate on the same 2,056 compatible
matches, including 946 girls' matches. Girls' Brier loss improves in fourteen of
fifteen measurable event groups versus 0.25; Overberg Leg 1 remains the small
regression identified in the sweep. Platteland girls improve from 39/72 to 49/72
correct favourites, and U12 girls from 21/43 to 30/43. Coverage is unchanged.

Both the local current snapshot and twelve monthly histories were rebuilt and
verified with the active coefficient and current policy/source fingerprints.
The history builder checked convergence for every monthly comparison component.
Focused verification passed 31 unit tests / 180 assertions and 12 isolated
SQLite feature tests / 103 assertions. The event-cap implementation has no diff.
