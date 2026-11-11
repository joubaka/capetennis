# Ball-level classification

Ball-level ratings classify evidence independently from age or score. The owning
fallback rules are in `BallRatingModel`; `BallRatingContext` applies the same rules
to finishing fields and display contexts.

User-confirmed competition formats (2026-11-11), for boys and girls:

- Exact U9 B and U10 B divisions use Orange across all tournaments.
- The rule applies to match evidence, published finishing fields and badge
  contexts, including shared courts recorded as generic `standard`.
- Generic `standard` courts also use explicit Red or Orange category/event
  labels instead of masking that format.
- Explicit Red, Orange, Green or Yellow playing conditions resolve the actual
  ball level before this division fallback. A combined A/B or multiple-age
  category does not establish an exact U9 B or U10 B division.

This decision broadens the earlier named-event defaults for Wilson U9 B,
Ceres U9 B and Overberg / West Coast Trials U10 B. Event names are no longer
required to establish the exact division's Orange fallback.

Changing a classification requires incrementing `BallRatingModel::VERSION` so
saved calculations cannot silently retain the old evidence buckets. A code fix
alone does not rebuild saved current ratings or history; use the existing
canonical refresh workflow after the appropriate authorization. No player profile,
canonical result, publication gate or rating-confidence policy should be changed
to repair a classification.

The personal history page selects the highest valid ball level in the latest
saved month (Green/Yellow, then Orange, then Red). A requested historical cohort
or a recorded starting pathway stage does not override that current level.
When testing separation, inspect the saved curves and evidence registry for
each ball level, while checking that the displayed evidence stays in the current
level and private sources remain redacted. Progression uses each level's own
score; an Overall score cannot fill a missing ball-level score.

An authorized recorded move advances Red to Orange or Orange to Green/Yellow.
Group 10–20 is suggested placement for early matches. Within the saved rating
window, the move tracks published played singles with a recorded match date
on or after the move date. The destination score stays pending until its own
saved fit includes at least one of those matches. Earlier ratings and finishing
fields do not substitute for post-move match evidence.

## Directory-to-detail navigation

The Super Admin rating directory must carry its resolved `ball_type` when
opening a player detail. Red, Orange and Green/Yellow details use the selected
saved ball rating; a missing rating must not fall back to Overall. Explicit
comparison-group links retain that group and withhold a mismatched estimate.
Reload and comparison-evidence links retain the same context. This is a
display/navigation rule, not a classification change or a reason to recalculate
saved ratings. Existing Super Admin authorization remains required.

Regression references: `tests/Unit/BallRatingModelTest.php` and
`tests/Feature/BallRatingPipelineTest.php`, with current-group selection in
`tests/Feature/PlayerRatingCurrentGroupTest.php` and pathway rules in
`tests/Feature/PlayerPathwayTest.php`.
