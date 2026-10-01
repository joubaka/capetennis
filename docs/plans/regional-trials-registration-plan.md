# Regional Trials and team participation implementation plan

Status: approved for local implementation, 2026-10-01. No commit, release, production migration, email dispatch or payment-provider mutation is authorised.

## Product boundary

One region hosts its own Trials across age/gender categories and selects combined A/B/C squads for an externally hosted competition. This application manages Trials results/rankings, selected rosters, optional communications, participation and payment administration. It does not host the external competition or its rankings.

## Accepted decisions

- Private nominations become registrable only after publication. Any eligible authenticated sponsor may register; profileless nominees must first resolve a player profile without granting ownership.
- One Trials fee across categories, regional event dates/deadlines, paid-only draws. Late paid nomination registration and category moves are permitted only before draw creation.
- Online payment, regional EFT account/proof upload and scoped administrative verification/manual collection; retain canonical financial services and existing refund rules. Proof alone is not payment. Keep actor/time/reference audit records and private documents.
- Per-category competition formats resolve final finishing positions. Automatically conclude completed Trials and publish/version exact-position rankings. Authorised corrections recalculate rankings and flag affected selections.
- Admin explicitly generates private drafts after conclusion, selecting A/B/C tiers for all age/gender groups. Each tier contains six per gender/category, minimum two players of colour inclusive of merit selections. Top four merit, remaining places satisfy the minimum then merit. Mark required vacant slots if insufficient eligible players; finalisation with vacancies is permitted.
- Regional admins may record audited colour declarations and adjust drafts; validate criteria. Finalise and publish all tiers together, separately from optional invitation sending.
- Higher tiers use lower-tier players for proposed replacements; only the lowest has four reserves per gender/category. Admin approves promotions/backfills, preserving paid state and no second participation fee.
- Invitations may target all/categories/individuals/statuses. Subject/body fully editable, saved reusable templates, exact rendered/recipient preview. Player plus distinct linked-parent contacts, one combined email per address. Missing/failed recipients flagged; manual retry only.
- All links land on the event page and highlight exact player/category. Any authenticated eligible user can respond; log actor/time. Admin may reverse decisions with reasons/history. Decline flags replacement, without automatically changing roster.
- Optional scheduled reminders require approved editable content/audience/timing; re-resolve status before send. No outbound communication during development.
- One region participation fee uniform across squads, separate from Trials fee. Standalone reserves pay only on promotion; current squad players do not repay when changing tier. Shared participation response/payment deadlines. Overdue players flagged without releasing their place.
- Public pages expose final rosters, not proofs/payment/response history. Private details visible only to scoped managers and relevant responding/paying actors.
- Withdrawals preserve results and financial history. Remaining fixtures become losses; admin may retain format position, put last or exclude from Trials rankings. Reuse existing refund process.

## Delivery stages and acceptance

1. Registration safeguards: event-scoped fee permissions, uniform Trials fee, protect used nominations/categories, null-safe statuses, lifecycle totals, draw/category gates, mail compare-and-set. Regression: wrong-role/event denial, lifecycle conservation and record counts.
2. Private EFT/manual collection: receiving account setup, private payer uploads, manager review/reject, manual receipt, exact amount and finalisation via canonical services. Regression: upload/view tampering, duplicate verification, provider handoff exclusion, audit/ledger integrity.
3. Trials conclusion/position rankings: format-resolved final placements, completeness gate, immutable revisions, correction review flags. Regression: incomplete/duplicate/missing positions, withdrawn last/excluded, recalc only affected event.
4. Draft squads: tiers/groups/slots, quota algorithm/placeholders, review/edit and atomic all-team finalisation. Regression: merit quota count, uniqueness, vacancy classification, private drafts and stale finalisation.
5. Participation/replacements: optional response actions on event page, audit/reversal, proposal approval, paid promotion continuity, deadlines flagged only, existing refund integration.
6. Invitations: combined contact previews, fully editable templates, manual retries, approved schedules with fresh audience/status resolution. Regression: stale preview, escaping/privacy, dedup/grouping, idempotent send claims, no lifecycle downgrade.
7. End-to-end verification: focused tests serially with isolated runtime/database, complete feature suite for cross-cutting registration changes, formatter, diff check, routes, Blade, relevant browser QA. Report local evidence separately from release/live evidence.

## Compatibility and implementation constraints

Keep legacy series rankings/team-selection behaviour for other event types. Use an explicitly Trials-scoped lifecycle rather than weakening ranking publication or sensitive-payment permissions globally. Preserve pre-existing profileless-nomination work. New schema is exercised only in isolated testing; list required migration files in the handoff, never apply production migrations automatically.

## Progress

- Stages 1–6 implemented locally: registration safeguards, private EFT/manual receipts, automatic finishing-position revisions, private/finalised squads, responses and approved replacements, participation payments/refunds, editable invitation previews/templates/reminders.
- Results corrections invalidate affected manual-position approvals before checking category completeness. Existing squads are flagged for review, not silently replaced. Unsupported or ambiguous placements require an admin finishing-position decision in the existing results workspace.
- Paid promotions retain the original entitlement/order. Displaced players are withdrawn through canonical payment services. Remaining Trials fixtures resolve withdrawal losses, including later qualifiers and double-withdrawal bracket feeders.
- PayFast refunds persist an immutable original-payment claim before dispatch. Confirmed retries finish locally; uncertain outcomes require scoped admin evidence reconciliation. Late provider replies cannot overwrite admin-confirmed recovery. Development/provider tests use mocks.
- Final isolated verification and its limitations are recorded below. No release or external action has occurred.

## Verification and operational handoff

- Final isolated SQLite in-memory Trials/registration pricing run: **140 passed, 1,316 assertions**. Additional canonical draw/Trials regression run: **87 passed, 264 assertions**, including automatic observer refresh and missing PayFast configuration. These runs overlap and their counts must not be added together.
- Complete feature run: 1,651 passed, 13 skipped, 12 failed. Two Trials failures used classes loaded before the final edits and pass in the final focused run. Nine failures originate in unchanged legacy team-selection helper activation code: `moveFromHelperTeamsToPrimaryTeam($locked, suppressInvitationMail: true)` omits the required selection-import argument. The pricing failure was traced to a random factory deadline that sometimes closed registration; the pricing-test fixture now explicitly uses a zero-day closing offset. The complete feature suite was not rerun after those scoped fixes and is not claimed green; legacy failures remain outside this implementation scope.
- Route registration, Blade compilation, changed-PHP syntax and `git diff --check` verified. A formatter is not installed in this checkout; no dependency was installed for formatting.
- Independent read-only quality/security reviews were completed and their scoped blocking findings corrected. No authenticated browser/mobile acceptance, live PayFast/EFT settlement, production MySQL concurrency check or live mail delivery is claimed. Normal local MySQL was unavailable; new schema was exercised only in isolated tests.
- No commit, push, deployment, production migration, publication of real squads, actual refund or outbound message was performed.

Required schema, in order (inspect individually before any future deployment):

1. `2026_10_01_000001_add_profileless_trial_nominations.php` — preserves the pre-existing profileless-nomination work.
2. `2026_10_01_000002_create_trial_programmes.php` — regional settings, immutable ranking runs, drafts and squad slots.
3. `2026_10_01_000003_trial_manual_receipts.php` — private registration proofs and reconciled receipts.
4. `2026_10_01_000004_trial_communications.php` — templates, approved previews and editable reminder schedules.
5. `2026_10_01_000005_trial_participation_payments.php` — per-player participation entitlement, proofs and receipts.
6. `2026_10_01_000006_trial_selection_reviews.php` — replacement proposals and withdrawn ranking dispositions.
7. `2026_10_01_000007_trial_refund_attempts.php` — separate participation withdrawal deadline and durable provider refund claims.

Admin journey after the schema is made available: create one-region Trials and age/gender categories → configure the common Trials fee and regional programme/payment/deadlines → nominate and publish → optional previewed invitations → sponsor registration and verified payment → existing draw/scoring workflow → automatic complete finishing rankings or resolve flagged positions → choose A/AB/ABC tiers and generate all drafts → review colour declarations/vacancies and finalise together → optional participation invitations and payments → flagged follow-up/replacements/refunds. Use the programme's refund-recovery page for uncertain or locally incomplete provider refunds.
