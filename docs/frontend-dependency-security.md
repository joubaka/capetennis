# Frontend dependency security

The frontend CI gate runs the audit-wrapper tests, vendor merge regression,
live npm audit and production build. Keep the audit fail-closed: an unavailable
registry or a newly discovered advisory is not a clean result. The existing
named, expiring advisory exception is not permission to allow other findings.

## Shepherd's embedded merge helper

Shepherd 12.0.6 retains its MIT license and published browser distribution.
It embeds an older `deepmerge-ts` implementation, so overriding the npm
dependency alone does not patch the browser code. The npm override and Yarn
resolution pin the patched helper to 8.0.0; the scoped webpack loader replaces
the embedded helper's sole entry point with an import from that patched helper.
All three tour merge call sites use this entry point. Unknown distribution
shapes fail the build for explicit review instead of silently skipping the fix.

Keep the package-root import so webpack selects the ESM distribution matched
by the loader. Include Shepherd and its patched helper in Babel processing.
When changing either package, verify the actual published files, merge behavior
and generated bundle, not just an empty audit report. Regression coverage is in
`tests/Frontend/shepherd-safe-merge.test.cjs` and
`tests/Frontend/vendor-bundles.test.cjs`.

## Release assets

Deployment synchronizes checked-in public assets without running npm. Update
the affected generated bundles, styles/fonts and their manifest entries after
a verified production build. An isolated build proves compilation but does not
by itself replace the browser code served in production. Preserve unrelated
public assets and test the emitted tour, KaTeX and translation entry points.
Local dependency work never authorizes a commit, deployment or live operation.

## Verified local checks

On 2026-11-16 the scoped dependency changes produced a clean npm audit without
expanding its exception, and the full isolated production build completed.
Audit-wrapper tests passed (6); the pinned Shepherd merge regressions passed
(2), covering callbacks/arrays, prototype safety, recursive inputs and unknown
distribution rejection. The emitted bundle smoke exercises real tour navigation,
KaTeX inherited-trust protection and local-resource translation initialization.
Public vendor files match their updated manifest hashes. Repair-library
validation passed 3 tests and 418 assertions.

The related bracket/governance CI failures were invalid success-path fixtures:
score mutations need the reviewed revision after fixture setup, and scheduling
needs the owning Event. Their corrected fixtures and score-revision regressions
passed together: 50 tests and 230 assertions on PHP 8.5 with isolated SQLite.
No application authorization or stale-write protection was relaxed.
