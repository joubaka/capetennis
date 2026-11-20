# Practice camp invitation planning

The practice camp is an independent event. Source team selections establish
evidence for invitation review; Cavaliers Primary School Trials and Interpro
workflows, entry rules, checkout ownership and previous declines do not carry over.

## Stage one

Administrators open **Practice camps** from the administration navigation.
A Super Admin can create or reopen the single Cavaliers Practice Camp 2027 draft.
Its initial dates are **7–9 January 2027, provisional**. The event is unpublished
and registration is closed. Venue, contact, fee and capacity are not confirmed.
The private plan stores a null fee; the legacy backing event requires a numeric
amount and retains zero only for schema compatibility. This is not a free-entry
offer and must not be used to open registration before pricing is reviewed.

Choose a source event, one of its regions, then individual teams or all teams in
that region. Review the roster players and separately labelled historical
declines/withdrawals before adding selected candidates to the private draft.
This does not create public nominations, team places, registrations or mail.
Only Super Admins or assigned administrators of both source and camp may use the
data. Convenor/scoring access alone does not grant access.

Teams use `EventTeamScope`; a shared region alone cannot associate a team with an
event. Source events and saved candidates are reauthorised on every operation.
UUID revisions protect concurrent draft edits. Source fingerprints require a new
review when the projected roster/selection evidence changes.

Existing player IDs deduplicate across teams and retain each source. Imported
roster slots use stable team/slot keys and remain separate even if a profile link
exists; linking or a matching name alone does not establish that identity. Match
these records before creating actionable invitations. Source data and account
links are never changed by the planner.

Historical review uses the latest active draft/sent selection import, not a merge
of superseded campaigns. Missing managed selection history is labelled as missing,
not evidence that nobody declined. Current roster membership is not described as
proof of having played. The planner does not calculate the original merit-ranking
cutoff, infer ages or automatically qualify historical declines. Manual addition
sequence numbers are never shown as merit rankings.

The product rule for the next stage is that coach nominations for this year's
U8/U9 players, moving to next year's U9/U10 groups, require organiser approval
before invitations. Coach links, approval and email delivery are not implemented
in stage one. Invitation sending and publication need their own reviewed action.

## Local setup and verification

The exact schema path is
`database/migrations/2026_11_20_120000_create_practice_camp_plans.php`, explicitly
listed in `deploy.config`. The allowlist is not permission to run it in production.
After applying that migration to the local database only, the idempotent command
`php artisan camp:prepare-2027 --actor=<existing-local-super-user-id>` creates or
reopens the private local event. The command refuses non-local environments.
The normal UI POST performs the same narrowly scoped creation after authorization.

Owning implementation: `PracticeCampPlanner`, `PracticeCampPlannerController`,
`PracticeCampPlan` and `backend/practice-camps`. Regression evidence:
`tests/Feature/PracticeCampPlannerTest.php` and `DeploymentConfigTest`.
