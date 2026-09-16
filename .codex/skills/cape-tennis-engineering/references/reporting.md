# Caretaker reporting

## Daily run

Follow `docs/caretaker/DAILY_WORKFLOW.md`. Remain read-only and quiet when there is no meaningful change. Do not fix findings during a scheduled run.

## Evidence labels

Use only labels supported by evidence:

- `confirmed`: directly observed in this run;
- `inferred`: reasoned from observed evidence;
- `blocked`: could not verify, with the missing prerequisite named;
- `historical`: taken from an older record and potentially stale.

## Handoff

Use `docs/caretaker/HANDOFF_TEMPLATE.md`. Include the user-approved scope, files changed, checks actually run, results, remaining risks, and the next action requiring approval. Omit empty sections rather than padding the report.
