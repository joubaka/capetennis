# Local player-change sandbox

Created 16 October 2026 in **local loopback database `ct` only**. Real Eden event 242 and its players were not edited. These are disposable, synthetic fixtures; no real payment, provider request, email, queued job or public registration was created. Both events and every team remain unpublished, with event sign-up closed.

Open the **editable normal Teams page** while signed in as your existing super-user:

- [Main sandbox — event 258](http://localhost/ct/public/backend/event/258/teams)
- [Read-only regional preview — event 258, region 141](http://localhost/ct/public/backend/team-selection/events/258/regions/141/manager-preview)
- [Separate fixture-lock sandbox — event 259](http://localhost/ct/public/backend/event/259/teams)
- [Read-only fixture-lock preview — region 142](http://localhost/ct/public/backend/team-selection/events/259/regions/142/manager-preview)

Use the same local origin/port as the sidebar if it differs from the links. Select **LOCAL TEST Eden - Player changes**. Most teams deliberately use the same `noProfile` raw-roster plus linked-profile mirror structure as Eden. Profile-only teams are secondary cases. The preview never saves; use the normal Teams page for changes. As super-user, normal administrative extras remain visible; the read-only preview demonstrates the restricted regional presentation. No real user was assigned a new role or region. Region ID is **132**; event-region IDs are **141/142**. Capacity and other retained administrator powers can differ from the restricted regional view.

## Suggested manual sequence

| Team | Starting scenario | What to try and expect |
|---|---|---|
| **01 Raw unpaid** (842) | Three raw names, no profiles | Move RawSecond up/down; remove it; add a typed first/surname. Only one name per occupied rank. Other rows stay intact. A repeated removal cannot delete another row. |
| **02 Linked unpaid** (843) | Rank 1 vacant; rank 2 linked **LinkedVisible Sandbox**; underlying imported text is **ImportedOld Different** | The list should show the linked name once, without a second imported-name row. Open **Change player**: blank name fields; the original remains until Save. Close or leave without saving: nothing changes. First save **Duplicate Target**: reject and preserve the original, its link and rank. Then save **Replacement Example**: replace atomically at rank **2**, despite rank 1 being vacant; the row becomes a raw name, its roster link/mirror is cleared, and the original profile remains in the system. Siblings stay unchanged. |
| **03 Linked paid** (844) | LinkedPaid Sandbox; synthetic wallet-paid order 546 | Try Remove or Change player: reject, preserving identity, order and paid history. Up/down is a distinct action and may remain allowed before competition; paid does not mean all ordering is frozen. |
| **04 Reserved unpaid** (845) | ReservedUnpaid Sandbox at rank 2; order 547 reserves R40, R60 due | Change to a valid new raw name or remove: close the old unpaid checkout and release its R40 reservation through the canonical payment service. Replacement stays at rank 2. No wallet debit/refund or provider request is needed. To inspect rollback first, enter a duplicate sibling name before a valid save: original checkout and reservation should remain. |
| **05 Handoff** (846) | HandoffProtected Sandbox; order 548 marked handed off locally, R100 due | Remove/Change must reject and preserve the original. This is a recorded in-flight state only; no PayFast request was sent. Do not continue checkout or manually reconcile this synthetic order. |
| **06 Profile unpaid** (847) | ProfileUnpaid at rank 2, ProfileSibling at rank 3 | Change player uses **profile search**, not raw-name fields. Search **LocalCandidateAlpha**, select and save. Same rank, no fake profile or duplicate raw row. Search LocalCandidateBeta/Gamma for subsequent add/change tests. |
| **07 Profile paid** (848) | ProfilePaid Sandbox; synthetic wallet-paid order 549 | Remove/Change reject; old identity and paid order stay intact. |
| **08 Raw typo** (849) | **Typoo Unlinked**, no linked profile | Open **Edit name**: fields are prefilled with Typoo / Unlinked. Correct the first name to **Typo**, then **Save name**. This corrects the same raw row in place: original slot ID, rank, mirrors and history remain. Closing without Save changes nothing. Stale forms or a duplicate same-team name reject without overwriting. Linked rows use **Change player**, not Edit name. The fixture team label still says name correction limits; the new in-place correction is now supported. |
| **09 Empty raw** (850) | No rows | Add a first/surname directly; no profile/account needed. |
| **10 Empty profile** (851) | No rows | Search an unused LocalCandidate profile and add it; maintain profile-team semantics. |
| **11 Full raw** (852) | Five raw names, five places | An assigned regional organizer must be refused a sixth name. The signed-in super-user retains the canonical administrator capacity override and may append; do not treat that as a failed regional guard. Test duplicate names on a team with space (01/02), so capacity does not mask the duplicate check. |
| **90/91 Fixture lock** (853/854, **event 259**) | Two raw teams, private draft tie | Regional preview should explain the fixture-history lock and disable roster actions. Actual Remove/Change/order guards reject. Full administrators retain their existing planned-draw add/adaptation authority; this is not a claim every administrator action is frozen. |

For stale-screen checks, open team 02 in two tabs **before** replacing. Save in one tab, then submit the old form in the other: it must fail rather than overwrite the new occupant. The fixture is not automatically reset after manual testing.

## Payment fixture boundaries

- Synthetic payer only: unverified `.invalid` account, no roles, random unknowable password; no login is provided. No existing user wallet or ownership links were changed.
- Canonical `LedgerService` credit R500; two canonical wallet settlements R100 each. Initial derived balance **R300**; three wallet transactions. Reservation R40 is not another debit.
- A raw name with **no linked profile cannot originate a canonical team checkout**. There is therefore no fabricated “paid unlinked name” case.
- Canonical wallet finalization currently marks the linked **TeamPlayer mirror and order** paid; it does not synchronize the no-profile row's legacy `pay_status` flag. Team 03 intentionally retains the actual canonical result (raw flag 0, mirror/order paid 1), and the real paid-order guard must protect it. The seeder does not forge that flag or provider settlement evidence.
- Initial counts: **2 events, 1 synthetic region, 13 teams (10 no-profile), 21 raw slots, 8 profile/mirror slots, 11 profiles, 4 orders, 1 synthetic payer/wallet, 3 wallet transactions, 1 private draft tie**. Eight no-profile teams are in the main event, two in the fixture-lock event.

## Re-running safely

`php scripts/seed-local-player-change-scenarios.php` checks the hard local guards and reports existing scenario IDs/counts. `--apply` creates the sandbox only when it does not exist. Once present, either invocation is read-only: it **does not reset, duplicate or overwrite** anything you have manually changed. There is intentionally no delete/reset option.

Seeding uses process-local Mail/Queue/Notification fakes, suppresses payment-notification domain events, and blocks stray HTTP requests. These controls do not change the web application's configuration. Initial seed verification did not consume any roster actions.

**Later on 16 October 2026:** the requested interactive sidebar tests consumed and changed these fixtures. See [sidebar results and final database reconciliation](local-player-change-sidebar-results.md) for current state and fixes. The initial scenarios above describe the starting fixtures; rerunning the seeder will not restore them.
