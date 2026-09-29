# Authority and safety

## Authority levels

### Automatic: read-only

- Inspect code, configuration, documentation, Git state, routes, tests, and logs already in the workspace.
- Run non-mutating diagnostics and produce evidence-based reports.
- Triage risks and propose scoped next steps.

### Supervised: local edits

- A direct request to implement, fix, change, build, refactor, or continue supplies approval for the bounded local task; do not ask for a second approval before starting or between normal local steps.
- Edit application code, tests, documentation, and local-only tooling for that task.
- Run proportionate focused tests, formatters, builds, route and Blade checks, and local browser QA, then fix failures caused by the change without another approval gate.
- Do not assume the task authorizes unrelated cleanup or broad refactors.
- Audit, investigation, diagnosis, review, explanation, and planning requests remain read-only unless implementation is also requested.

### Explicit approval required

- Commit, push, create or update a pull request, modify dependencies, or prepare an approved production migration list.
- Run broad or unusually expensive checks when they are likely to disrupt normal local work.

### Fresh manual authorization every time

- Deploy or operate production infrastructure.
- Execute production migrations or change production data.
- Publish rankings, draws, fixtures, schedules, results, or other public records.
- Send email, notifications, announcements, or other communications.
- Change PayFast, mail, credentials, secrets, roles, security controls, or production feature switches.

An earlier approval does not persist to a later production action unless the user explicitly scopes it that way.

## Safety rules

- Never reveal `.env` values, merchant credentials, SMTP credentials, tokens, or personal data in reports.
- Use counts, identifiers safe for the task, and redacted evidence.
- Do not modify production-like databases during audits. Confirm the active environment and database before any test that writes records.
- Treat withdrawal, refund, wallet, payment, ranking publication, and bulk-mail paths as high risk.
- Do not use controller-level direct mutations to bypass canonical services.
