# Team event draws deployment

The release adds event-scoped scoring rules and six/eight-player pairing presets, explicit home/away positions, immutable draw snapshots, readiness preview, tie operations, scheduling conflict checks and canonical standings. Existing draws retain their stored formats and scoring behaviour.

## Required additive migrations

- database/migrations/2026_10_04_010000_add_team_rubber_pairing_positions.php
- database/migrations/2026_10_04_020000_add_team_event_rules_and_snapshots.php

Both paths are in deploy.config. The first adds nullable pairing JSON columns; the second creates team_event_rules and adds nullable scoring/format snapshot JSON columns to draws. Neither migration rewrites existing records. Do not roll these migrations back after new event data has been saved; rollback removes the new data.

## Deployment checks

1. Wait for CI on the exact pushed commit to pass.
2. Confirm the effective team_draw_v2 flag for the intended event. Event and administrative overrides take precedence over FLAG_TEAM_DRAW_V2; no production flag was changed by this work.
3. Use the standard deployment preflight and approve its exact pending migration paths. The list above does not establish which migrations are pending on the server. Do not run all pending migrations blindly.
4. Deploy the approved SHA using the existing supervised deployment workflow, which syncs public JS/assets, refreshes caches and restarts workers.
5. Verify event rules, format editing, preview, tie operations and score entry as the intended organiser/scorekeeper; verify published standings as a guest and ensure private draws remain private. Confirm an existing historical draw is unchanged.

## Local evidence

Full preset acceptance covers all 45/60 fixtures for three six/eight-player teams, exact pairings including imported players, complete scheduling/scoring, correction/deletion/re-entry, point totals, public gates and historical preservation. Desktop/mobile Chrome rehearsal covers format editing, creation, validation/publication, schedule editing, score actions and public standings.

Local verification does not establish deployment, production migrations, effective production flags or live acceptance.
