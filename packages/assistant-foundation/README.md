# Assistant foundation 0.1.0

Portable PHP primitives shared by design, without application models, credentials, permissions or database access. Cape Tennis loads this local package through `AppServiceProvider`; no root dependency or lockfile changes are needed. The package has its own Composer metadata so it can later be moved into a private versioned repository. That repository has not been created or published.

- `ProposalFingerprint::make()` binds confirmations to a normalized exact proposal.
- `FeedbackClassifier::classify()` provides deterministic improvement categories, not learned model weights.
- `DurationEstimator::estimate()` provides a conservative empirical baseline from verified, non-interrupted observations. It never changes timed-match rules or saves schedule state.
- `DurationEvaluation::evaluate()` measures that baseline with whole later events held out and never enables it automatically.
- `Contracts\IntentProvider` keeps interpretation data separate from application actions.

Browser recording and application-specific presentation remain application adapters. Each consumer must enforce its own authorization, retention, provider consent and action validation. No shared conversation store or credentials are included.

## TeamSheet adoption

TeamSheet remains unchanged. Adopt this version in a separate local implementation after verifying equivalent feedback categories and adding its own package loading. Do not copy Cape Tennis event tools or permission rules into TeamSheet. Once both consumers pass their regressions, publish a reviewed version and replace local package loading with a pinned Composer dependency.
