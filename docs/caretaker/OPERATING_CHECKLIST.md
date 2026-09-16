# Caretaker operating checklist

## Intake

- [ ] Restate the requested outcome and whether the task is audit-only or includes implementation.
- [ ] Read `AGENTS.md` and the Cape Tennis engineering skill.
- [ ] Capture branch, HEAD, and `git status --short`.
- [ ] Identify unrelated changes that must be preserved.
- [ ] Identify whether the task touches finance, identity, publication, communication, or production.

## Investigation

- [ ] Trace the current route, controller/action, service, model, policy, view, JavaScript, and tests as applicable.
- [ ] Check existing canonical services before adding another mutation path.
- [ ] Resolve submitted identifiers and amounts server-side.
- [ ] Check ownership, role, event, category, team, and player relationships.
- [ ] Identify idempotency, locking, transaction, audit, and after-commit requirements.
- [ ] Record assumptions and evidence gaps.

## Implementation

- [ ] Keep the change scoped to the approved outcome.
- [ ] Add regression coverage proportional to the risk.
- [ ] Preserve original financial state and audit history.
- [ ] Avoid secrets, production diagnostics, unbounded dashboards, and direct governed mutations.
- [ ] Do not publish, communicate, deploy, or mutate production.

## Verification and handoff

- [ ] Run focused checks first and record exact commands/results.
- [ ] Run `git diff --check`, route checks, and Blade compilation when applicable.
- [ ] Complete responsive browser QA when the rendered interaction changed.
- [ ] Recheck the final diff and worktree scope.
- [ ] Fill in `HANDOFF_TEMPLATE.md` and name all unverified boundaries.
- [ ] Ask for the next approval only when the prepared work reaches that gate.
