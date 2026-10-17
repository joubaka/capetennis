# Local sidebar player-change results — 16 October 2026

**Passed:** interactive sidebar testing of synthetic events **258/259**, followed by read-only SQL verification and **14 focused tests / 205 assertions** (PHP 8.5, 1:29.455). The main event is predominantly Eden-style no-profile teams. Real Eden event 242 was not edited; its 16 teams remain present. Nothing was deployed, published or sent to a payment provider.

## Sidebar checks

The signed-in account was the existing **super-user**. Restricted regional authorization and capacity denial were verified in isolated automated tests, not by granting a new browser role. Preview remains read-only.

| Scenario | Observed result |
|---|---|
| Raw team 842 | Up/down and boundary validation worked. Remove → Cancel preserved the row; Confirm remove deleted it. Adding SidebarAdded Example filled the freed rank 2. Player order tab changes saved. |
| Linked display duplicate | Adding LinkedVisible Sandbox initially exposed a duplicate-validation bug because the underlying imported name differed. Fixed and retested: add now rejects, with existing rows unchanged. The temporary bad fixture row was removed through the UI. |
| Linked replacement 843 | Duplicate replacement rejected. Valid Replacement Example saved at rank 2, preserving the unrelated rank-1 vacancy and siblings; linked mirror 7268 cleared to player 0. Original profile retained. Submitting the old form was blocked. |
| Paid no-profile 844 | Remove and Change player rejected. Moving paid player down/up preserved the paid order, player and mirror identity. |
| Reserved unpaid 845 | Duplicate replacement rolled back with reservation R40 intact. Valid ReservedReplacement Example saved at rank 2; old unpaid checkout closed and reservation released canonically, with no wallet debit. |
| Handoff no-profile 846 | Remove and Change player rejected; recorded in-flight order untouched. |
| Ordinary profile 847 | Search replacement to LocalCandidateAlpha saved at rank 2. Removing ProfileSibling left its existing rank-3 slot empty. |
| Paid profile 848 | Remove and Change player rejected; paid state and beneficiary preserved. |
| Raw Edit name 849 | Duplicate rejected; Typoo → Typo corrected the same original slot **1482**, rank 1. A later deliberate remove/re-add for the stale-form test created new raw slot **1497**. This ID change came from that separate test, not Edit name. |
| Empty raw 850 | FirstRaw Example added at rank 1. |
| Empty profile 851 | Already-rostered Alpha rejected across the event; unused Beta added at rank 1. |
| Full raw 852 | Super-user's existing administrator append override allowed a sixth name; it was removed through the UI, restoring five. Automated regional actor test rejects the extra player and preserves count. |
| Fixture-lock event 259 | Players and Player order showed the lock explanation, with regional roster actions unavailable. Private fixture teams remained unchanged. |
| Stale form feedback | Missing old row is safely rejected; client now displays “This player list changed. Refresh the page and try again.” Input is preserved without retry. |

## Fixes made during these checks

1. Replaced unsupported native browser removal confirmation with accessible inline **Confirm remove / Cancel**, retaining confirmation and duplicate-submit protection.
2. Unified raw-name duplicate checks for Add, Edit name and Change player. They now compare normalized raw names **and linked profile display names**, under the existing locks; failed replacements roll back.
3. Replaced internal model-not-found text with friendly stale-list feedback.

## Read-only database reconciliation

The baseline snapshot was captured **after the first successful Typoo correction** (audit count 1). The original seed guide records its pre-edit spelling. Final comparison found:

- Raw slots **21 → 22**; profile/mirror slots **8 → 9**, consistent with the explicit additions/removals above. Team/rank positions remain unique; replacement mirrors at rank 2 are empty rather than duplicate visible players.
- All **11 synthetic profiles remain**; **0 user-player ownership links** were created. Old linked profiles were not deleted or renamed.
- Exactly **4 orders** remain. Protected orders **546** (paid raw), **549** (paid profile) and **548** (handoff) are **exactly unchanged** across every captured field.
- Order **547** remains unpaid, total **R100**, with reservation **R40 → R0**, due **R60 → R0**, and `withdrawn_at` recorded. Wallet-debited and PayFast-paid remain false.
- Synthetic wallet is exactly unchanged: balance **R300**, three original ledger transactions (R500 credit and two R100 debits). No extra credit, debit or refund appeared.
- Scoped team audit count **1 → 37**, recording the performed changes.

Paid no-profile rows retain the existing canonical legacy distinction documented in the [scenario guide](local-player-change-scenarios.md): the raw flag can remain 0 while mirror and order are paid 1. Tests preserved that actual state rather than forging a raw paid flag.

## Verification and limits

The settled source passed the complete `RegionalRosterTest` plus the two scoped preview tests: **14 tests, 205 assertions**. PHP and shared JavaScript syntax, compiled shared Blade PHP, route registration and `git diff --check` passed. Independent security and quality reviews covered the fixes; browser mutations were limited to synthetic fixtures. A full feature suite was not rerun for these bounded changes.

The sandbox is now **consumed and changed** by these manual tests. The seeder reports current data and intentionally does not reset it. The guarded read-only [inspection script](../scripts/inspect-local-player-change-scenarios.php) can inspect it again. Temporary before/after JSON and test logs were removed after this reconciliation.
