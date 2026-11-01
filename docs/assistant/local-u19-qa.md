# Local U19 assistant verification — 1 November 2026

The assistant remains restricted to `super-user`. No commit, push, deployment, production mutation, publication or outbound tournament communication was performed.

## Fixes

- Friendly missing-draw, venue and court guidance on both client and server. Foreign event identifiers still fail before setup guidance.
- A lightweight, authorized workspace at `/backend/events/{event}/assistant` avoids rendering the event overview's finance/roster graph during assistant testing. It uses the standard backend layout.
- Safe application/database errors hide SQL and provider response bodies. Failed confirmation responses advise checking current state rather than claiming a rollback.
- The assistant now uses the canonical day-batch scheduling contract with graph-aware Flexible Monrad rounds. Existing bookings remain fixed and only unsaved assignments enter apply. Timed match rules remain authoritative.

## Local setup

The local runtime was verified as `local`, MySQL at `127.0.0.1`, database `ct`. The missing nullable `registrations.competition_replacement` column was added using only the reviewed migration `2026_10_30_120000_add_competition_replacement_to_registrations.php`. Pending migrations were not run wholesale.

With explicit user approval, the existing local JTA Gemini credential was configured in Cape Tennis's ignored `.env`. No credential values were printed or committed. `local_first` mode enables the explicit AI checkbox and voice transcription; permanent credentials remain server-side and HTTPS verification is enabled.

## Live provider evidence

- A small real Gemini interpretation request returned `start_time=09:00` for “Start at 9 instead”.
- A short locally synthesized WAV was transcribed correctly by Gemini. No ambient microphone recording was used.
- In the actual authenticated browser, an AI request for both U19 draws produced a valid preview. A follow-up changed the start field to 09:00, retaining the date, draw selection and finish time.

Physical microphone hardware/permission behavior still needs the user's spoken test. Automated recording, cancellation, denial and transcript-review checks cover the UI separately.

## U19 data and preview evidence

The original local event 255/draw 1519 contained 43 fixtures, 42 result rows, no venues and no saved bookings. Its completed state correctly yielded no schedulable matches. Published Paul Roos courts 1–6 setup was copied into its previously empty local venue configuration. Its existing fixtures/results were preserved.

Under the user's remote-to-local copy authorization, public live snapshots were downloaded from `/flexible-monrad/1523/live` and `/flexible-monrad/1524/live`. A separate **private** local QA event was created: event 263, **LOCAL QA — U19 public snapshot 2026-11-01**, with boys draw 1521 and girls draw 1522. It contains 72 copied public match states, 99 set-result rows, 16 visible public court bookings and 28 anonymous test entrants. No real player/contact records, payer links, entries, payments or financial ledgers were copied or fabricated. Queue/mail were faked and HTTP was disabled during import.

Only public court time, court label and venue were available in the snapshot. Local QA booking durations/gaps use explicit test defaults of 60/5 minutes; these are not claims about remote booking duration settings.

Both draws were exercised separately: boys proposed 7 new assignments, girls 3, each retaining the event's 16 existing bookings, with no unscheduled matches. The combined authenticated browser preview proposed **10 new assignments and retained 16 bookings**. Saving remained disabled until explicit acknowledgement; no acknowledgement or save/publication was submitted. The follow-up timing request worked.

Canonical fixture, result, booking and public-assignment hashes remained identical before/after direct local service previews. Browser testing submitted preview/conversation requests only; no confirmation action was used.

## Automated evidence

- **86 focused PHPUnit tests, 1,163 assertions passed**, including assistant, access, provider failure, timing, deployment configuration and Feed-in scheduling regressions.
- Responsive browser checks passed at 320, 390, 768 and 1280 pixels. Added checks cover friendly empty selections without requests, retained booking counts, safe server-error messages and recording isolation.
- PHP/JavaScript syntax, route registration, Blade compilation and `git diff --check` passed.
- Independent quality and financial/security reviews found no remaining material source issue after corrections.

The browser screenshot API was intermittent; a successful actual U19 capture was saved as `storage/framework/testing/u19-assistant-live-preview.jpg`. The actual preview was visually inspected and its accessible table/fields verified. The responsive fixture screenshot is a separate automated artifact, not proof of a physical microphone or production deployment.
