# Cape Tennis event assistant backend

All routes live under the existing authenticated backend group at `events/{event}/assistant`. Each controller action independently authorizes `event.manage` for the route event. Conversation/proposal/feedback queries also bind the current actor and event. A confirmed new individual draft additionally requires `individual-draw.create`. No payment, mail, account-linking or role tools are exposed.

## Endpoints

| Method | Suffix | Body | Result |
| --- | --- | --- | --- |
| GET | context | none | availability, minimal event/draw/venue context, dates, court labels, defaults, personal event preferences, capabilities |
| GET | conversation/{uuid} | none | last 30 messages owned by this actor/event, only while still authorized and unexpired |
| POST | message | `message`, optional `conversation_uuid`, `use_ai` (default false), `options` | answer/clarification and optional exact proposal |
| POST | preview | `options`, optional `conversation_uuid` | schedule proposal without fixture, booking or publication writes |
| POST | publication-preview | `options`, optional `conversation_uuid` | separate proposal for exact saved match times |
| POST | generation-preview | `draw_id`, optional `conversation_uuid` | initial prepared individual Round Robin generation proposal |
| POST | confirm | `proposal_uuid`, `confirmation_token`, `confirmed:true` | canonical apply result, or same result on duplicate confirmation |
| POST | feedback | `message_id`, `rating:helpful|unhelpful`, optional `reason`, `comment` | owned, deduplicated feedback |
| PUT | preferences | `preferences`, `confirmed:true` | replace current admin's preferences for this event |
| POST | transcribe | multipart `audio`, `use_ai:true` | editable `transcript`; never submits the conversation or saves a schedule |
| GET | improvements | none | current actor/event aggregate feedback categories only |

Route names are `backend.event-assistant.{suffix}`, with `conversation` and `publication-preview` corresponding to the named methods in the controller.

## Options

Normalized intent options:

```json
{"date":"2026-11-07","start_time":"08:00","end_time":"17:00","duration":60,"court_gap":5,"player_rest":60,"draw_ids":[1],"venue_ids":[2],"courts":["1","2"]}
```

The existing form's `start`/`end` datetime strings are also accepted and normalized. Server validation discards execution flags and unrecognized keys; it resolves date, draw, venue, court and round scope against the current event. Time windows cover one event day. Start/end and duration/rest/gap defaults are shown and remain editable. Existing bookings stay fixed; rescheduling/replacing booked fixtures is intentionally delegated to the existing calendar workflow. Maximum 200 draws/venues and 2,000 selected fixtures; publication bounds the event fixture graph before the canonical service loads it. Events longer than 31 days require the existing workspace rather than an assistant date guess.

Scheduling uses `EventVenueScheduleService::preview` directly. It never calls the controller preview or playoff preparation. Saving calls canonical `apply` with immutable stored options and the expected revision. A preview with unresolved matches cannot save. Played, locked, timed-match and dependency rules remain enforced by the scheduler. Individual round-robin ordering remains canonical.

## Proposal response

```json
{"conversation_uuid":"uuid","message":{"id":1,"content":"Review..."},"options":{},"clarification":null,"proposal":{"uuid":"uuid","type":"schedule","confirmation_token":"64-character-token","fingerprint":"sha256","expires_at":"ISO-8601","can_confirm":true,"preview":{"matches":[],"unscheduled":[],"revision":"sha256"}},"suggestions":[]}
```

Other types are `publication` (preview contains exact saved rows/count/date), `draw_draft` (preview name/discipline/event-wide empty scope), and `draw_generate` (preview name, per-group entrants/matches, count, and scope). Token hashes, fingerprints, actor/event scope and expiration are checked server-side; a new conversation request invalidates outstanding confirmations. Confirmed actions are idempotent under row locks and canonical transaction/locking. Publication rechecks published/unlocked draw lifecycle under locks as well as the canonical working/public revision. Saving and publication never send announcements.

`draw_draft` creates only a new, unpublished individual setup record using the same policy/type defaults and model lifecycle as the existing creation workflow. It never selects entrants, seeds, regenerates brackets or changes existing draws. The result contains `setup_url` for the canonical guide and `generated:false`.

`draw_generate` supports only initial individual Singles Round Robin fixtures for a category-scoped draw already prepared in the guide. It independently requires `generateFixtures`, unpublished/unlocked state and zero existing fixtures, bookings or publication snapshots. Each group needs two or more unique active paid category entrants with one real player each, unique positive seed positions and matching draw roster membership. No player can appear in two entries. Counts are bounded before loading relations. Confirmation locks the draw, settings, groups, memberships, category/entry rows and player links, revalidates eligibility, compares the complete seed/membership/settings fingerprint and delegates to canonical `DrawGenerationService::generateRoundRobin`. Expected fixture counts must match or the transaction rolls back. It does not prepare playoffs, choose seeds, schedule or publish. Other formats and regeneration remain explicit guide handoffs.

## Provider and learning boundaries

Ordinary local requests do not call a provider. Paid interpretation requires `use_ai:true` on that message; voice requires its own explicit opt-in and is blocked in `local_only`. The current implementation uses configured Gemini credentials through the reusable `IntentProvider` contract; permanent credentials stay server-side and TLS verification stays on. Only event structure and the explicitly submitted question go to interpretation, not participant records or conversation transcripts. Audio is sent for transcription without durable application storage. Unknown provider actions/invalid payloads cannot execute.

Feedback categories use the portable deterministic classifier; aggregates are not model training. Explicit preferences are user/event scoped. Duration observation and suggestion have separate endpoints/services owned by the parent integration task. No automatic model training, prompt rewriting or cross-project conversation sharing is enabled.

## Persistence and verification

Migration: `database/migrations/2026_11_01_120000_create_event_assistant_tables.php`. It must be reviewed and allowlisted in `deploy.config`, and must not be run in production without separate authorization. Conversation expiry is 30 days, proposal expiry 15 minutes. Expired conversations cannot be read or confirmed; bounded per-scope cleanup plus the scheduled parent pruning command physically removes child messages/proposals/feedback. Preferences remain until explicitly changed.

Focused coverage lives in `tests/Feature/Assistant/EventAssistantTest.php`, `tests/Unit/Assistant/AssistantInterpreterTest.php`, and provider tests. Run with isolated SQLite in-memory or an isolated testing database. Live provider calls, production setup and physical microphone testing are separate evidence.
