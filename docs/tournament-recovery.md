# Tournament error recovery

## Operating rule

Never repair progressed tournament data directly in the database. Use the normal score correction while no dependent match has been played. If a round-robin correction would invalidate generated playoffs, use **Tournament recovery** from the score modal.

## Round robin already progressed to playoffs

1. Enter the corrected completed round-robin score and try to save it.
2. When the progression guard blocks the edit, choose **Tournament recovery**.
3. Review the exact number of playoff fixtures, played results, and scheduled matches that will be reset.
4. Enter a reason and type `RECOVER #<draw id>` exactly.
5. A super-user must perform the action if a playoff result already exists.
6. The system atomically captures a before snapshot, removes every playoff fixture/result/schedule, applies the corrected RR result, captures an after snapshot, and locks and unpublishes the draw.
7. Review every group standing, unlock the draw, complete the normal progression review, regenerate the playoffs, review scheduling, and publish again.

If the recovery itself was wrong, a super-user can use **Recovery history → Restore before snapshot**. Restore requires a new reason plus `RESTORE #<case id>`, verifies both the stored checksum and the current after-state fingerprint, captures a pre-restore snapshot, and leaves the restored draw locked and unpublished.

## Other supported correction paths

- RR before fixed playoffs: save or delete the result normally; source-position fixtures are recalculated from current standings.
- Standard knockout: correct an upstream winner only while every dependent fixture is unplayed. If play has continued, delete results from the latest affected match backwards, then correct the source result and replay progression.
- Flexible Monrad: use its controlled recursive downstream reset and re-enter the corrected result.
- Scheduling-only errors: edit the order of play; result correction does not require draw regeneration when participants and progression are unchanged.

## Detection

Run the read-only audit before publication and after any recovery:

```text
php artisan draw:integrity-check --draw=<id>
php artisan draw:recovery-audit --draw=<id>
```

The recovery audit checks impossible winners, result/participant mismatches, current standings against sourced playoff slots, suspiciously stale fixed brackets, stuck recovery cases, and snapshot checksums. Use `--json` for machine-readable output.

## Deliberate limitations and next tranche

The persistent snapshot workflow currently restores the individual fixture/result/order-of-play graph for RR-to-playoff corrections. The snapshot also retains settings, registrations, and group assignments as forensic evidence, but restore deliberately does not overwrite those master records.

The following destructive flows still require their own typed-confirmation snapshot integration before they should be considered equivalent to RR recovery:

- forced regeneration of published/completed team ties or rubbers;
- one-action recursive recovery for a played standard knockout chain;
- persistent before/after restoration for Flexible Monrad resets;
- roster, group assignment, or scoring-rule changes after results exist.

Until those integrations are delivered, retain their existing guards and do not use force overrides as a substitute for a recoverable operation.
