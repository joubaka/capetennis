# Ball-level classification

Ball-level ratings classify evidence independently from age or score. The owning
fallback rules are in `BallRatingModel`; `BallRatingContext` applies the same rules
to finishing fields and display contexts.

Standing product decision (2026-11-16): Red, Orange and normal CTR (Green/Yellow)
must always remain separate. Never combine their evidence or scores, use another
pool as a fallback, or present a mixed-ball calculation as CTR. This applies to
ratings, lists, badges, history, match comparisons and pathway guidance.
"Overall" means all ages within Green/Yellow CTR, not all ball levels.
Earlier combined-model behavior is superseded by this decision; recording the
rule does not establish that every existing consumer has been corrected.

User-confirmed competition formats (updated 2026-11-15), for boys and girls:

- Single-age U9 divisions use Orange across all tournaments, including A, B,
  combined divisions within U9 and labels with no division letter. Exact U10 B
  divisions also use Orange; U10 A/B combined divisions retain the normal fallback.
- The rule applies to match evidence, published finishing fields and badge
  contexts, including shared courts recorded as generic `standard`.
- Generic `standard` courts also use explicit Red or Orange category/event
  labels instead of masking that format.
- Explicit Red, Orange, Green or Yellow playing conditions resolve the actual
  ball level before this division fallback. Multiple-age categories do not
  establish an Orange fallback.

This decision broadens the earlier U9 B-only fallback to all single-age U9
divisions. Event names are not required to establish the U9 or exact U10 B
Orange fallback. The rule classifies competition evidence, not a player's age.

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

The Super Admin directory offers Red CTR, Orange CTR, CTR and Overall (all
ages). CTR means the saved Green/Yellow `standard` ball graph. Overall is the
same graph and the same scores across all ages in one list; it clears any
retained age filter and age-based ordering. Red CTR and Orange CTR each retain
their separate, age-independent graph. Overall player links use `standard`
so opening a profile cannot switch the displayed score to the combined model.
Historical `overall` comparison-group links must resolve within the current
Green/Yellow graph or withhold the rating when the group no longer matches.
They never authorize a combined-ball graph or a fallback to another pool.

Region choices remain stable across ball-level changes and represent recorded
affiliations of valid rated players. Selecting a region without players at the
new level returns an empty list. Arbitrary and unrated-only region values remain
invalid. Name and evidence filters preserve existing list positions; age, gender
and region select the comparison population before ranking. New filter
submissions reset pagination, while pagination preserves the active filters.

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
