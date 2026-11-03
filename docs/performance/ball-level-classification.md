# Ball-level classification

Ball-level ratings classify evidence independently from age or score. The owning
fallback rules are in `BallRatingModel`; `BallRatingContext` applies the same rules
to finishing fields and display contexts.

User-confirmed competition formats (2026-11-03), for boys and girls:

- Overberg Trials U10 B: Orange.
- West Coast / Westcoast Trials U10 B: Orange.
- Ceres tournament U9 B: Orange.

These are event-and-division fallbacks, not a general U10 B or U9 B rule.
Explicit ball labels and court metadata retain precedence. Other divisions and
unrelated events retain their existing classification. Wilson U9 B retains its
existing Orange fallback.

Changing a classification requires incrementing `BallRatingModel::VERSION` so
saved calculations cannot silently retain the old evidence buckets. A code fix
alone does not rebuild saved current ratings or history; use the existing
canonical refresh workflow after the appropriate authorization. No player profile,
canonical result, publication gate or rating-confidence policy should be changed
to repair a classification.

Regression references: `tests/Unit/BallRatingModelTest.php` and
`tests/Feature/BallRatingPipelineTest.php`.
