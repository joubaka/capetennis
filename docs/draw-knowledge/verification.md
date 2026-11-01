# Running draw knowledge regressions

Use `phpunit.draw-knowledge.xml` from the repository root. It selects existing behavioural regressions plus the new combined Monrad examples; it does not duplicate the full draw suite.

```powershell
# Pure compiler/progression examples; no database or Laravel boot.
php vendor/bin/phpunit -c phpunit.draw-knowledge.xml --testsuite DrawKnowledgeUnit --fail-on-skipped

# Laravel integration checks: use an isolated disposable test database.
php vendor/bin/phpunit -c phpunit.draw-knowledge.xml --testsuite DrawKnowledgeFeature --fail-on-skipped
```

The feature suite uses `RefreshDatabase` and can recreate tables. Never run it against application or production data. Its config forces `APP_ENV=testing`, loopback MySQL `ct_testing`, array cache/session/mail (including named mail transports) and a null logger. Its bootstrap rejects mismatched environment settings, clears database URL/socket overrides and selects an absent test-only configuration-cache path so cached application settings cannot override the target. Compiled views use `storage/framework/testing/draw-knowledge-views` instead of the application's shared view cache. Coordinate serial database execution with other work; a shared `ct_testing` name alone does not provide concurrency isolation. Do not change dependencies or copy live data simply to make the suite run.

The pure suite directly exercises compiler/progression results using fictional sparse IDs. The feature suite checks group rotation, valid drawn scores, review snapshots, in-place progression, walkovers, all-player playoffs, two-stage RR, separate group winners, rebuild safety, timing, publication and event isolation. Additional catalogue tests remain useful for a targeted change without making this curated run unnecessarily broad.

## Evidence from this task

Initially PHPUnit could not read `vendor/autoload.php` inside the sandbox, so a temporary App-namespace-only bootstrap verified the pure graph classes and was then removed. Subsequent checks showed this was a sandbox readability limitation, not reliable evidence of a missing dependency. The configured pure suite passed through the normal autoloader and was independently repeated by the quality reviewer. After adding the final guarded bootstrap, the configured suite passed again using an approved execution outside that file-read restriction. A temporary permission diagnostic was reverted; no dependency changes or lasting filesystem permission changes remain.

- New `DrawKnowledgeRegressionTest`: **3 tests passed, 21 assertions**.
- New examples plus `FlexibleMonradCompilerTest`, `FlexibleMonradChallengesTest` and `FlexibleMonradRollingChallengesTest`: **21 tests passed, 43,605 assertions**.
- PHP syntax and scoped whitespace checks passed.
- Timeline editing and bracket layout: **17 JavaScript tests passed** with `node --test tests/Frontend/schedule-timeline.test.cjs tests/Unit/tennis-bracket-layout.test.cjs`.
- The initial handoff did not include a database run. The user's follow-up authorized the integration run against local `ct_testing`, with database-refresh runs executed serially.
- The first integration attempt stopped at 61 tests / 1,256 assertions with a fixture setup error: `RoundRobinGroupCompetitionTest` called nonexistent `Venue::factory()`. The test now creates a fictional venue directly. No runtime application change was needed.
- The focused rerun also exposed stale expectations allowing workflow changes after scores and expecting controller conflicts for policy-denied locked/published draws. Those expectations now match current safeguards, with an explicit assertion that rejected scored changes preserve results and bookings. The corrected class passed **4 tests / 20 assertions**.
- The final complete command, `php vendor/bin/phpunit -c phpunit.draw-knowledge.xml --fail-on-skipped`, passed **129 tests / 45,314 assertions** on PHP 8.5.0 and local MySQL `ct_testing`: **108 integration tests plus 21 pure unit tests**, with no skipped tests. The run included the final isolated view-cache and forced array cache/session settings. Independent quality and authorization reviews found no remaining issues in the changes.

Route registration, Blade compilation and browser acceptance cannot be claimed from a pure run. No application routes, views, schema definitions or runtime draw behaviour are changed by this library; integration runs rebuild only the local disposable test database. Nothing is committed, pushed, deployed or published by this task.
