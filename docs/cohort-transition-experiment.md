# Private cohort-transition experiment

This is an unsaved, local research comparison, not a replacement rating, member feature or all-age scale. It has no routes, scheduler entry, model-version change, snapshot/cache writer or confidence mutation.

Run with PHP 8.5 from the project root:

```powershell
php -d memory_limit=512M scripts/validate-cohort-transitions.php --events=233,241
php -d memory_limit=512M scripts/validate-cohort-transitions.php --events=233,241 --assume-compatible-context
```

Both commands require a local environment and a resolved loopback MySQL connection. Split/read/write and host-array connections are refused. The whole evaluation runs within the existing consistent read-only transaction. The CLI compares complete saved-snapshot digests before/after and refuses a result if another process changes saved ratings during the run. It does not stop or change the existing ratings-only task. Reports contain aggregate metrics, source fingerprints and explicit caveats, without player names.

For bounded execution, independent historical predictions are computed only for identities appearing in the target event's cohort-qualified canonical rows. All participant IDs are routed, including rejected/unfinished rows; winners, scores and rejection reasons do not select candidates. Routing never prunes the historical matches, peer network or native references used in any fit, and never chooses a target component from heldout opponents. This is a report-equivalent performance optimization: predictions for people absent from every scored row cannot affect the report. Regression coverage compares full and routed reports, including an irrelevant historical candidate and reversed heldout outcomes. The service retains an unrouted internal comparison path for this verification; no extra CLI model option is introduced.

## Fixed prototype policy

Parameters were fixed before running this experiment. The September/October results had already been examined during earlier investigation, so these are retrospective, previously reviewed comparisons, not blind final tests. Future unseen results are required before promotion. Historical sources are original eligible played singles in U10/U11, same gender as the supported U11 boys/U12 boys/U12 girls target. Whole heldout, overlapping, future, invalid-date and unpublished events are excluded. The history window is 730 days, fit decay is 180 days, and per-player/event incident volume is budgeted before decay. Original fixture identities are deduplicated. Ordinal finishes, old ratings and old Trials anchors are not inputs.

Current canonical readers do not attest historical ball/court compatibility. Therefore the default profile explicitly abstains with `context_unverified`; it does not pretend those fields were verified. `--assume-compatible-context` is a deliberately labelled research assumption for these age/gender contexts. It does not infer compatibility from an age label or approve any estimate for publication. A future verified-context collector is necessary before operational use.

The conditional model fits dated monthly historical player states from original outcomes. Native target-cohort pre-event strengths are fixed calibration references only; their native likelihood is not fitted again. Native predictions and confidence counts remain exactly unchanged. A historical source belongs to a younger cohort, so it is not duplicated as a target-cohort fixture. Same-player temporal links are weak assumptions, not matches or wins: their precision is `scale / (1 + gap_days / 90)^2`. An explicit unobserved target-time state is linked to the candidate's last historical state. A weak neutral ridge of 0.02 applies to every fitted state, including historical and unobserved target-time states; this is an assumption, not proof of improvement or a calibrated uncertainty interval.

The model evaluates continuity scales 0.5, 1 and 2; scale 1 is the fixed central estimate. Candidate output requires at least two native reference peers and exactly one target component. It never merges target components, chooses one after viewing heldout opponents, or transfers old 0–100 numbers. Histories reaching multiple native components are withheld. Processing is bounded to 2,000 relevant source rows / 500 historical network players per candidate; exceeding bounds withholds that candidate explicitly.

`robust` additionally requires two own historical source events, no loss of eligibility when any historical supporting player other than the focal player (including unrated intermediaries and all their raw rows) or any supporting network event is removed, and strength range no greater than 0.5 across the prespecified sensitivity fits. This is a conservative structural screen, not calibrated accuracy. The `exploratory_only` profile permits available but fragile candidates and is reported separately. Leave-out fits rebuild focal connectivity and eligible references. Reports distinguish total player-removal failures, reference-peer failures and non-reference intermediary failures. At most 200 complete player/event removals are fitted per candidate; exceeding that processing bound withholds the candidate rather than reporting partial robustness. No parameter or component is selected using holdout winners.

## Interpreting the report

Existing native pairs, newly covered pairs and combined descriptive results have separate denominators. The same-pair metrics must be identical by construction; coverage expansion cannot be called an accuracy gain on those existing pairs. New-pair metrics are compared with a neutral 50/50 reference and reported separately from the selected existing sample. Brier/log loss are descriptive and probabilities remain uncalibrated. Repeated players and events are dependent. Results use current corrected identities/publication records retrospectively rather than reconstructing historical availability.

Historical match counts are supporting evidence only; current-cohort played counts, confidence labels, canonical rating policy and saved records are unchanged. Multiple candidates may share source matches: aggregate historical-match incidents are not a count of distinct fixtures. Do not promote this model based only on the two already reviewed September/October holdouts; additional future events and independent context review are required.
