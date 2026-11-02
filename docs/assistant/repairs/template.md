# Repair record template

Add the following fields to a record in `catalogue.json`, after the fix is verified:

- `id`: unique `RK-000` style identifier.
- `title`: concise symptom a person would search for.
- `scope`: applicable formats and workflows; excluded cases.
- `symptoms`: how to recognise and reproduce the problem.
- `cause`: demonstrated root cause; distinguish possible diagnoses from established facts.
- `fix`: verified procedure using current canonical services and workflows; protected state and stop conditions.
- `verification`: actual dated checks and results, plus unverified boundaries. Do not label merely referenced tests as passed.
- `limits`: permission requirements, unsafe cases and remaining uncertainty.
- `assistant_action`: `guide_only`. This catalogue does not grant executable capability.
- `sources`: one to ten repository-relative source/document paths.
- `tests`: one to ten repository-relative regression paths.

Text fields must be nonempty and at most 2,000 characters. References must be repository paths under `app/`, `tests/` or `docs/`, without traversal or URLs. Keep real identities, event values and secrets out of records. Prefer updating a matching repair over adding duplicates.
