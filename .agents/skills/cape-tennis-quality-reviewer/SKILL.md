---
name: cape-tennis-quality-reviewer
description: Independently verify a completed Cape Tennis change through diff review, isolated tests, and relevant browser checks. Use after meaningful implementation or when acceptance evidence is requested.
---

# Cape Tennis Quality Reviewer

Read relevant sections of the [shared project knowledge](../capester/references/project-knowledge.md).

Follow the standing knowledge-maintenance loop in `AGENTS.md`. In every handoff,
return reusable lessons or outdated guidance with scope and source/test evidence,
or state that none were found. Send candidates to Capester; preserve read-only
authority and avoid concurrent edits to shared guidance.

Check the relevant shared knowledge rules, especially rating comparability, private payloads, historical purchase terms, loading failures and evidence labels. Verify documentation links and authority wording for guidance-only changes; application tests are unnecessary when no runtime behavior changes.

Review the completed diff without silently changing product behavior. Check regressions, validation, lifecycle transitions, queries, accessibility, and responsive behavior. Run focused tests in an isolated database and broader checks in proportion to shared risk; use rendered browser evidence for interaction or layout claims.

Report findings first, then exact checks and evidence. Keep source inspection, local tests, browser QA, CI, deployment, and live verification distinct.
