# Player Plus read-only readiness report

Run `php artisan player-plus:readiness` from an authorized Cape Tennis server CLI. The command prints aggregate JSON. It has no apply, refresh or repair mode and exposes no web endpoint. It does not change settings, relationships, memberships, orders, cancellations or saved ratings, and does not contact a payment provider or send notifications.

The report describes the database and saved files of the runtime in which it runs. A local result does not verify production. No player/account identifiers, email addresses, provider tokens, credentials or reconciliation messages are included.

## Reading the report

`nightly_refresh` reads the private pipeline's saved status, stage, cycle date,
processed/failure counts, bounded prerequisite attempts, update time and generic
reason. It never exposes the private cursor, player, reference or context hashes.
This is a saved operational observation, not proof of a working host cron or a
complete audience refresh. Missing state remains unavailable; reading it starts
no calculation.

- `schema`: whether the relevant tables exist. Missing or unreadable schema makes dependent sections unavailable; it does not trigger migrations.
- `offers`: service/schema readiness, configured and effective sales switches, recurring-provider configuration presence, and annual/monthly prices in cents. A configured switch is separate from effective availability. Invalid or absent prices are reported with the same safe fallback amounts used by checkout; raw invalid setting values are omitted. Provider configuration presence does not verify provider connectivity or settlement.
- `saved_ratings`: availability through the canonical publication/policy-aware snapshot reader, current-policy agreement, saved date/staleness, Overall CTR coverage and confidence counts. Coverage counts describe saved ratings, not the narrower public participant list shown to a member. Confidence describes evidence support, not calibrated rating accuracy.
- `saved_history`: whether a policy-valid saved history can be read and its captured publication context remains available. An absent publication manifest is explicitly unknown. `stale` is true when its saved date precedes today in South African time or its source fingerprint differs from the canonical current source fingerprint. `source_fingerprint_current` reports only that comparison boolean; raw fingerprints are never output. Changed sources do not automatically make a readable history unavailable when its publication context remains valid. The same-group ratio counts consecutive calendar-month Overall CTR snapshots for the same player: same-group pairs divided by all adjacent pairs. Missing months do not form pairs. This is a continuity indicator, not an accuracy or performance measure. No source calculation or refresh is performed.
- `billing_review`: counts of orders carrying reconciliation notes, pending checkouts and subscriptions with a cancellation request that has not been confirmed cancelled. The counts are review queues, not a settlement or refund decision. Private reconciliation notes and provider tokens are never selected for output.
- `relationships`: legacy-owner and pivot relationship counts with existing accounts/players, active Plus player count, and deduplicated active account/player pairs. A matching `player.account-linked` audit requires the player subject and target account to match the pair. Missing matching evidence is **unknown**, not proof that a relationship is invalid. Historical ownership and audit records do not by themselves certify entitlement. The command never links, unlinks, grants, suspends or revokes access.

An `available: false` section is not replaced with invented zeros. The JSON contains generic failure reasons rather than exception messages that might disclose private values. Review missing schema or stale evidence separately through the established application workflows and their normal authorization requirements.

Regression coverage: `tests/Feature/PlayerPlusReadinessReportTest.php` verifies aggregated values, unavailable optional schema, unchanged row contents, absence of write queries, and omission of private sentinel values.
