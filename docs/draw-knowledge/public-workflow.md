# Public draws, schedules and fixture navigation

## Public summary contract

`PublicDrawPresentation::summary()` derives availability, the earliest visible
match and venue names from `SchedulePublicationService::publishedRows()`. Public
labels and empty states must not infer availability from `oop_published` alone or
from working bookings. This retains event/draw publication, fixture ownership and
first-match-only visibility. The event page passes its already-loaded timing rows
to avoid a separate schedule load for every card.

Configured draw venues are shown as **Planned venue** only when no public rows
are visible. With public rows, show their venues exclusively. The earliest
currently visible match is labelled **Published matches from**, with its date and
year; it is not necessarily the historical opening time or a player's start time.

Authorised Flexible Monrad working/draft previews retain their explicit permission
checks. A preview must not change the public publication badge into a claim that
times are available to guests.

## Navigation

Individual event draws precede event information and documents. Team and Trials
mobile draw lists precede event information too. Existing responsive draw IDs stay
unique. Incoming `#event-draws-match-times` or `#event-draws-mobile` is resolved to
the visible draw section, so returning from a draw or fixture does not focus a
hidden desktop panel on a phone.

Public RR and Flexible Monrad links support `#draw` and `#schedule`. Selecting a
tab updates the URL, including RR keyboard tab changes; changing the fragment
opens the corresponding panel. Return links go to the tournament's draw section.

## Verification

`PublicTournamentWorkflowTest` covers empty publication, published-vs-working
venues, dated summaries, unique targets/order, foreign fixture assignment and
first-match-only venue privacy alongside the existing publication tests.
`Draw/PublicDrawPresentationTest` covers public draft navigation and empty-state
rendering. Run on an isolated testing database and private compiled-view path.

The rendered browser fixture is generated from fictional records by:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='sqlite'
$env:DB_DATABASE=':memory:'
$env:LOG_CHANNEL='null'
$env:DB_URL=''
$env:DATABASE_URL=''
$env:CT_TEST_ARRAY_TRANSPORTS='1'
$env:PUBLIC_WORKFLOW_SNAPSHOT='1'
$env:VIEW_COMPILED_PATH=(Resolve-Path storage/framework/testing/public-journey-views).Path
$env:APP_CONFIG_CACHE=Join-Path $env:VIEW_COMPILED_PATH 'config.php'
$env:APP_ROUTES_CACHE=Join-Path $env:VIEW_COMPILED_PATH 'routes.php'
php vendor/bin/phpunit tests/Feature/PublicTournamentWorkflowTest.php tests/Feature/Draw/PublicDrawPresentationTest.php
node --test tests/Browser/public-roundrobin-display.cjs
```

Create the private view directory first; keep its config/route cache paths absent
or specific to this isolated run. Use Node 20+ with Playwright available (a bundled
runtime may require its executable and `NODE_PATH`);
the browser harness uses the installed Edge channel. Regenerate the HTML fixture
before the browser run. Browser checks exercise shipped RR rendering, keyboard
and fragment navigation, and real rendered event cards at 320, 390, 768 and 1450
pixels. Screenshots are local testing artifacts, not live or physical-phone
verification. These procedures do not publish, deploy or alter live records.
