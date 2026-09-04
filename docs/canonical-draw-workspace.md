# Canonical individual draw workspace

Implemented locally on 2026-09-03. Existing unrelated working-tree changes are preserved.

## Audit findings addressed

- Custom Monrad, Monrad-only and playoffs-only previously escaped the full draw hub into a standalone editor.
- Dashboard settings/players links led to competing management screens, including a players view without its required data.
- The older `draws.show` route displayed sample Alice/Bob matches instead of resolving the requested draw. It now uses draw-specific authorization and canonical navigation.
- Type-5 events chose the trials scheduler and data shape before considering the flexible format.
- The flexible public view did not display its separately published timetable. The order-of-play venue relation referenced a nonexistent model.

## Navigation contract

`backend.draw.roundrobin.show` is the canonical individual draw workspace entry. The URL name is retained for existing bookmarks; it now dispatches the appropriate format without redirecting flexible formats outside the workspace.

Shared header: title/status, Print, publication-aware Public view and Share, locking, Switch Draw and Back to Event. Shared tab fragments:

| Fragment | Round robin | Flexible formats |
| --- | --- | --- |
| `#groups` | Players and groups | Starting positions; generated roster is read-only |
| `#matrix` | Matrix / results | Existing bracket editor and results |
| `#schedule` | Schedule and venues | Timetable and the existing individual scheduler |
| `#settings` | Settings and notes | Name, format review and notes |
| `#print` | Existing print options | Same bracket/results renderer or timetable printing |

Switch Draw preserves supported fragments, including print. Scheduler/format pages retain the shared navigation and draw context. The existing standalone flexible editor route redirects to the canonical workspace. Its public and demo pages reuse the extracted editor partial; there is no second graph renderer or iframe.

Sharing only offers the public URL after publication. Flexible public responses omit schedule fields while `oop_published` is false. Schedule publication has a narrow exception to the legacy graph-mutation guard; graph publication, player assignment and scoring still use their format-specific services and guards. Notes use the existing `editNotes` endpoint.

## Verification and boundaries

- 68 focused feature tests / 1,039 assertions passed: canonical workspace, setup, flexible graph/scheduling, round-robin workspace, Head Office links and bracket presentation.
- A further 72 authorization/lock/Head Office tests / 124 assertions passed (140 feature tests and 1,163 assertions in total).
- Five JavaScript bracket tests passed; JavaScript syntax and Blade compilation passed.
- Authenticated local browser checks: actual Custom Monrad draw 1439 opens the full workspace; Schedule reaches the individual scheduler; print options open in both formats; switching from round-robin 1440 to Custom Monrad 1439 preserves `#print`.
- Browser print preview could not be inspected through the browser control surface; physical printing, saved PDF pagination, and mobile visual QA remain unverified.
- No live event registrations, fixture generation, results, schedules or publication flags were changed for browser QA. No deployment or production migration was performed.
- Event-wide bulk PDF exports, final-results import/rankings, and the separate result-write authorization findings in `draw-dashboard-integration-audit.md` remain separate work. Custom Monrad printing uses the per-draw canonical Print panel, not the bulk PDF path.
