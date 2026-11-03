# Cape Tennis standing agent roster

- **Capester:** user-facing engineering lead, scope owner, result reconciler, and final handoff owner.
- **Feature Developer:** owns one bounded feature or file group, follows `$capester`, and adds focused regression coverage.
- **Authorization and Financial Reviewer:** follows `$cape-tennis-authorization-reviewer` and independently challenges event, identity, ownership, invitation, money, publication, and audience boundaries.
- **Quality Reviewer:** follows `$cape-tennis-quality-reviewer`, reviews the completed diff, and runs isolated tests and relevant browser acceptance.
- **Caretaker:** follows `$cape-tennis-caretaker` for read-only health and readiness work unless explicitly assigned local implementation.
- **Release Guardian:** follows `$cape-tennis-release-guardian` only for explicitly authorized release assessment or operations.

Temporary specialists may cover draws, rankings, payments, imports, database design, frontend accessibility, performance, mail, or browser automation. Define their deliverable, edit authority, owned boundary, evidence requirement, and stop condition. Reuse live specialists during one task; recreate later roles from repository guidance rather than hidden chat state.

Durable guidance belongs in the narrowest suitable place:

Every role reads relevant sections of [project-knowledge.md](project-knowledge.md)
and follows its source pointers. Capester supplies the task's exact authority,
owned boundary, concurrent edits and unresolved evidence; historical chat context
does not replace that brief. Feature Developers inherit this through `$capester`.

Every specialist brief and final report includes the standing knowledge loop from
`AGENTS.md`: identify reusable verified lessons or superseded guidance and return
their scope and evidence, or report none. Capester alone coordinates shared
knowledge edits; reviewers check material changes. Read-only assignments return
candidates without edits. This applies to temporary specialists as well as the
standing roles and does not expand any role's authority.

- Broad product and safety rules: `AGENTS.md`.
- Agent behavior: the relevant skill.
- Conditional technical procedure: a skill reference.
- Executable invariant: regression tests.
