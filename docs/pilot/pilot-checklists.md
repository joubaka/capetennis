# Cape Tennis — Internal Pilot Checklists

> Version: 2026-07  
> Scope: Internal pilot only — no public tournament events.  
> Engine default: **hybrid** (canonical only on selected RR draws).  
> Fallback: **always enabled**.

---

## How to use these checklists

1. Run `php artisan db:seed --class=PilotEventSeeder` to create the 4 pilot events.  
2. Note the event IDs printed by the seeder.  
3. Work through each checklist in order using the pilot admin account.  
4. Record pass/fail/observation against each item.  
5. After each scenario run `php artisan pilot:report --event=<id>` to print a summary.  

---

## CHECKLIST 1 — Finance & Payment

**Pilot scenario:** `payment` event  
**Engine mode:** legacy (payments are outside engine scope)

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| F1 | Open payment pilot event in admin | Event loads, status=active | | |
| F2 | Verify 4 active paid registrations | CER list shows `status=active`, `pf_transaction_id` set | | |
| F3 | Verify 1 pending refund registration | `status=withdrawn`, `refund_status=pending` | | |
| F4 | Verify 1 wallet-refunded registration | `status=withdrawn`, `refund_status=refunded` | | |
| F5 | Verify 1 unpaid registration | `pf_transaction_id=null` | | |
| F6 | Run `php artisan finance:integrity-check` | Duplicate ITN `PILOT-PAY-DUPE` detected as warning | | |
| F7 | Issue bank refund for pending-refund registration | Status changes to `refunded`, amount recorded | | |
| F8 | Issue wallet credit for another withdrawal | Wallet balance increases by refund amount | | |
| F9 | Verify refund audit log entry | `PlatformAuditLog` has `refund.issued` action | | |
| F10 | Attempt double-refund | System rejects with error, no double credit | | |

---

## CHECKLIST 2 — Registration

**Pilot scenario:** any pilot event

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| R1 | View registration list for category | All seeded players appear | | |
| R2 | Withdraw an active registration (player-initiated) | Status → `withdrawn`, `withdrawn_at` set | | |
| R3 | Attempt to withdraw already-withdrawn registration | Error: "This registration is already withdrawn." | | |
| R4 | Admin withdraw a different registration | Status → `withdrawn`, audit log entry created | | |
| R5 | Verify `canWithdraw()` blocks non-owner | 403 / redirect with error | | |
| R6 | Withdraw after draw locked | `reason=draw_locked` returned | | |
| R7 | Verify `RegistrationWithdrawTest` passes | All 5/5 green | | |

---

## CHECKLIST 3 — RR Generation

**Pilot scenario:** `rr` event  
**Draw engine_mode:** `canonical`

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| G1 | Open RR draw in backend admin | Draw loads with `engine_mode=canonical` visible | | |
| G2 | Verify 2 groups of 4 pre-seeded | Groups A and B with 4 registrations each | | |
| G3 | Trigger RR generation via UI | Fixtures generated, no exception, audit log `draw.generated` | | |
| G4 | Check `engine_runs` table | Row with `engine_mode=canonical`, `canonical_success=true` | | |
| G5 | Check `platform_audit_logs` | `draw.generated` with `engine_mode=canonical` | | |
| G6 | Verify mismatch count = 0 | No mismatch rows for this draw | | |
| G7 | Check `PilotEvent.mismatch_count` = 0 | Still 0 | | |

---

## CHECKLIST 4 — Standings

**Pilot scenario:** `rr` event

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| S1 | Enter score for Group A fixture (6-3) | Score saved, standings update | | |
| S2 | Verify standings order is correct | Winner shown first by wins/sets/games | | |
| S3 | Enter score for second Group A fixture | Standings re-rank correctly | | |
| S4 | Enter score for Group B fixture | Only Group B standings change | | |
| S5 | Delete a score | Standings revert correctly, score_delete audit log written | | |
| S6 | Check `engine_runs` for standings operations | `operation_type=standings`, `canonical_success=true` | | |
| S7 | Enter duplicate score (same fixture, submit twice) | Second submission is idempotent or rejected cleanly | | |

---

## CHECKLIST 5 — Playoff Progression

**Pilot scenario:** `playoff` event  
**Engine mode:** hybrid

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| P1 | Enter R1 match score (6-4) | Winner advanced to parent fixture `registration1_id` | | |
| P2 | Enter second R1 match score | Winner advanced to parent `registration2_id` | | |
| P3 | Enter R2 match score | Winner recorded, `match_status=3` | | |
| P4 | Verify parent fixture correctly populated | Both `registration1_id` and `registration2_id` filled | | |
| P5 | Check mismatch log | Hybrid: compare canonical vs legacy output; 0 mismatches expected | | |
| P6 | Submit duplicate R1 score | No duplicate winner in parent fixture | | |
| P7 | Check `engine_runs` rows | `fallback_used=false` in all rows | | |

---

## CHECKLIST 6 — Rollback

**Pilot scenario:** `playoff` event

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| RB1 | Rollback R1 match result (delete score) | Parent fixture winner/registration cleared | | |
| RB2 | Verify fixture `match_status=0` after rollback | Reset to 0 | | |
| RB3 | Verify `DrawAuditLog` entry created | `score_deleted` logged | | |
| RB4 | Verify `platform_audit_logs` entry | `progression.reset` logged | | |
| RB5 | Attempt rollback on published draw | 403 returned, `success=false` | | |
| RB6 | Attempt rollback on locked draw | 403 returned | | |
| RB7 | Rollback R2 after R1 already rolled back | Safe — no crash, no orphan progression | | |
| RB8 | Re-enter score after rollback | Score re-saved cleanly, parent re-populated | | |

---

## CHECKLIST 7 — Publication & Scheduling

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| PB1 | Publish draw | `published=true` | | |
| PB2 | Attempt score delete on published draw (API) | 403, `message=Draw is published.` | | |
| PB3 | Attempt score delete on published draw (RR backend) | 403, `success=false` | | |
| PB4 | Attempt deleteIndResult on published draw | 403 | | |
| PB5 | Unpublish draw | `published=false`, mutations re-enabled | | |
| PB6 | Save court/time schedule for fixture | Schedule persisted | | |
| PB7 | API schedule summary returns scheduled fixtures | Correct fixture list returned | | |

---

## CHECKLIST 8 — Rendering

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| RN1 | Load bracket view for playoff draw | SVG/HTML bracket renders without error | | |
| RN2 | Load OOP for RR draw | OOP table renders correctly | | |
| RN3 | Download PDF fixture list | PDF generated, no exception | | |
| RN4 | Verify `engine_runs` for bracket rendering | `operation_type=bracket`, duration recorded | | |

---

## CHECKLIST 9 — Feature Flags

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| FF1 | Check `FeatureFlags::enabled('canonical_engine')` returns false by default | Correct per env config | | |
| FF2 | Set `FeatureFlags::setForEvent($eventId, 'canonical_engine', true)` | Per-event flag active | | |
| FF3 | Verify draw resolution picks up per-event flag | `effectiveEngineModeWithFlags()` = canonical | | |
| FF4 | Clear per-event flag | Reverts to hybrid | | |
| FF5 | Enable admin override: `FeatureFlags::enable('canonical_engine')` | All draws without overrides use canonical | | |
| FF6 | Disable admin override | Reverts to hybrid | | |
| FF7 | Run `php artisan platform:health-check` | Reports current flag states | | |

---

## CHECKLIST 10 — Fallback Testing

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| FB1 | Force canonical exception by setting draw to canonical with unresolved mismatch | Router falls back to hybrid, logs `engine.fallback` | | |
| FB2 | Verify `engine_runs.fallback_used=true` | Row recorded | | |
| FB3 | Verify `platform_audit_logs` `engine.fallback` entry | Written with `reason=unresolved_mismatches` | | |
| FB4 | Simulate mismatch threshold breach (>25% in last 2h engine runs) | Auto-downgrade to hybrid logged | | |
| FB5 | Verify after threshold breach draw still returns valid standings | Hybrid fallback output is correct | | |
| FB6 | Verify no data corruption after fallback | All fixture and result rows intact | | |

---

## CHECKLIST 11 — BYE Advancement

**Pilot scenario:** `consolation` event (6 players → 2 BYE slots)

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| BY1 | Verify BYE fixtures exist (null registration2_id) | 2 BYE fixtures present in main draw | | |
| BY2 | Run BYE advancement | BYE players advance to R2 without match | | |
| BY3 | Verify parent fixture populated correctly | BYE winner in correct slot | | |
| BY4 | Roll back a BYE advancement | Parent slot cleared, BYE fixture reset | | |

---

## CHECKLIST 12 — Platform Governance

| # | Step | Expected | Pass/Fail | Notes |
|---|------|----------|-----------|-------|
| PG1 | Run `php artisan platform:preflight` | PASSED | | |
| PG2 | Run `php artisan platform:health-check` | 0 critical (pre-existing drift excluded) | | |
| PG3 | Run `php artisan draw:integrity-check` | Only pre-existing issues, 0 new issues | | |
| PG4 | Run `php artisan platform:release-audit --since=1hour` | Shows pilot engine runs, 0 failed jobs | | |
| PG5 | Check `PilotEvent` counters after all scenarios | mismatch=0, fallback=0 for canonical draw | | |
| PG6 | Run `php artisan pilot:report` | Summary table printed for all 4 pilot events | | |

---

## Post-pilot sign-off

| Criterion | Required | Observed | Sign-off |
|-----------|----------|----------|----------|
| Canonical RR mismatch rate | 0% | | |
| Fallback events | 0 | | |
| Rollback corruption | 0 | | |
| Published guard blocked all mutations | YES | | |
| Finance refund integrity | 0 double-refunds | | |
| Duplicate ITN detected | YES | | |
| `platform:preflight` | PASSED | | |
| All target test suites | Green | | |
