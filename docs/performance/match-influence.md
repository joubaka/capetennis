# Estimated match influence on Overall CTR

The offline influence tool retains a saved sensitivity estimate for the latest
five eligible singles matches included in a player's saved Overall CTR. The
Player Plus overview instead uses the [chronological reconstruction](match-ctr-timeline.md).
Do not relabel this without/with sensitivity comparison as before/after match
ratings. It is also separate from the player's ball-level badge and the selected
month in rating history.

For each match, the offline calculator fits the same canonical Overall model at
the same saved date after omitting that one exact source. Estimated influence is
the saved baseline CTR minus the recalculated CTR without that match. Recency,
score-margin targets and per-event evidence budgets are recalculated normally.
The baseline must first reproduce the saved rating on the same comparison scale.

A positive value means that the saved CTR is higher with the match included; a
negative value means it is lower. This is not a historical before/after rating,
a number of points awarded for a match, or a causal measure of improvement. Each
match is omitted independently, so the five estimates must not be summed or used
to explain an entire monthly change. Values are rounded for display.

## Availability and safeguards

- Only canonical eligible played singles are considered; final placings are not
  match results. The list can differ from the latest entries on the Results tab.
- The latest evidence date orders rows. A stable source-ID tie-break does not
  claim a playing order for matches recorded on the same day.
- Removing a match must preserve the player's component, reference population,
  recent backbone and connection status. A changed scale, missing player or
  nonconverged fit produces an unavailable row rather than a numeric estimate.
- Display metadata comes from matching saved history evidence. Source, policy,
  publication and snapshot checks must agree before a result is shown.
- Member requests require an authorized player relationship and active Plus
  entitlement; the existing Super User preview boundary remains unchanged.
  The response contains only the selected player's five match summaries; graph
  inputs and other players' private rating history are never projected.

## Building and refreshing

After the canonical saved rating and monthly history are current, run:

```powershell
php artisan player-match-influence:refresh PLAYER_ID
```

The command generates a versioned, bounded private per-player artifact. It uses
an isolated consistent read and a lock, then rechecks the source context before
atomic replacement. It does not change canonical matches, ratings, rankings,
player links, entitlements, publication or financial records.

Page reads never fit the model, write the artifact or enqueue a calculation.
Changed source data, policy or snapshot context withholds old estimates until
the offline command is run again. The saved as-of date must also be today in
South African time; yesterday's artifact is withheld even if its inputs have
not otherwise changed. Regenerate after the rating/history rebuild
when this feature is required. There is no automatic all-player batch: workload
must be measured before scheduling up to six model fits per player.

Local verification does not authorize production execution or deployment.
