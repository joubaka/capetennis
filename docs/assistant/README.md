# Cape Tennis tournament assistant

The assistant is a **super-user-only pilot until testing and explicit approval for wider access**. The event workspace header exposes **Ask assistant** only to the `super-user` role, with existing `event.manage` checks retained. All assistant endpoints, including voice, history, feedback, generation and timing tools, enforce this role on the server. There is no date-based or automatic rollout, and the enable flag cannot grant access to other roles. Widening access requires a separate reviewed code change after approval.

It supports typed requests, optional voice transcription, conversational corrections, reviewed schedule proposals, separate order-of-play publication, empty individual draw creation, feedback and explicit timing preferences.

It also supports reviewed category copying between events. For example: `Copy categories from Panarottis/Spur Leg 1 to Legs 2 and 3, without creating duplicates.` Use exact event names; ambiguous or missing events require a corrected request. Event IDs are supported: `Copy categories from event #123 to event #124 and event #125`. Category copying is interpreted locally, including when **Use AI** is selected.

The preview identifies source and destination names/IDs, each category to add or skip, the resulting fee (including the destination event fee when no override exists), and ordering. Review it, acknowledge the changes, then choose **Copy categories**. The copy shares the existing category definitions and copies fee overrides and ordering only. Existing category IDs or equivalent names are skipped and their settings kept. Entries, nominations, publication flags, locks, draws and results are not copied. Changes to the source, destinations or categories invalidate the preview; repeated confirmation is idempotent. Trials reject copied fee overrides. One request supports up to ten destinations and 1,000 existing category rows across the selected events. Category and settings permissions are rechecked when confirming.

## Use

### Send a reviewed problem to Capester

When the separately configured support queue is enabled, **Send a task to Capester** opens a manual, reviewed description form. The local receiver delivers that text into a private inbox; delivery does not execute a fix or deploy code. See [the queue and receiver guide](capester-queue.md) for permissions, retry behaviour and rollout prerequisites.

Separately opted-in users can have unsupported tasks and **Needs improvement** feedback recorded automatically for background capability review. The feature is off by default, requires an explicit actor allowlist, and forwards only one linked instruction excerpt plus fixed metadata. It excludes drafts, feedback comments, conversation history and provider responses. Read [the capability-improvement and privacy boundary](capability-improvement.md) before enabling it. Future code improvements require tests, review and an approved deployment.

### Draft and review an email

Ask `Draft an email to parents about possible rain delays` using typed input or reviewed voice transcription. Select **Use AI** to generate prose with the configured provider. Only that instruction and the explicitly submitted, visible current draft are sent for drafting or revision; no roster, recipient list, previous chat transcript or event data is sent. Separate Codex/ChatGPT chats are not accessible to the application assistant: paste their draft into the editor if needed. The current editable draft is included when asking a correction such as `I meant Under 13, not Under 30; finish by 14:00 on Sunday`. AI text remains a draft whose facts must be checked. Without AI opt-in, the assistant opens a manual editor and preserves an existing draft; it does not claim to generate prose.

Edit **Email subject** and **Email message**, then explicitly choose **Email audience**. No broad audience is preselected. Available choices follow the event's canonical communication scopes; recorded player notification contacts can include primary and additional contacts, and are not a guessed list of parents. A direct email address is also supported. For region/team/individual targeting, copy the draft into the linked event email composer and choose its audience there.

**Review recipients and email** opens the existing full-page communication review with the exact sender, message, deduplicated recipients and missing-contact issues. No email is sent by drafting, changing the draft, or opening this preview. Only **Send email(s)** on that review page approves the canonical managed queue. The existing ownership, one-hour preview expiry, missing-contact acknowledgement, stale-recipient fingerprint, deduplication and mail rate limit apply. An assistant review also expires when its conversation expires or is deleted, and any new message or recipient preview in that conversation invalidates an older review. Unsaved browser-only edits are not submitted until another request or preview. Repeated send confirmation does not queue the approved batch twice. Queue submission and delivery are separate evidence; check communication history for outcomes.

Drafts are editable in the current panel; the normal assistant retention policy applies to draft metadata. Closing the editor leaves no automatic send instruction, and saying `send` in chat cannot approve mail. Starting a new conversation resets the editor. No new migration, mail transport or credentials are introduced.

The lightweight testing page is `/backend/events/{event}/assistant`; the assistant panel also links to it. Current local U19 test evidence and data-copy details are recorded in [local-u19-qa.md](local-u19-qa.md).

1. Open an event administration page and select **Ask assistant**.
2. Type, or choose **Speak**, stop recording, review the transcript and choose **Send**. Transcription never submits a message automatically.
3. For precise selection, open **Schedule details** and select an event date, draws, venues and timing. Calculate a preview.
4. Review identified match assignments and warnings. Acknowledge the changes and choose **Save schedule**. Existing bookings are kept fixed.
5. Use **Review selected order of play** for the saved date/draw selection. Publication has its own preview, acknowledgement and confirmation.
6. To prepare a draw, request `Create draw named Under 14 Boys`. This creates an empty draft after confirmation. Its link opens the existing guide for player selection, format and seeds. It does not pretend that an empty draft is a generated bracket.
7. For an individual round-robin draw with players, groups and seeds already prepared in the guide, select that draw and choose **Review round-robin generation**. Review group/fixture counts, then confirm initial generation. Existing fixtures, published/locked state, invalid or withdrawn/unpaid entrants and changed group/seed settings block generation. Other formats and regeneration remain in their established draw workflows.

Local phrases include `Schedule Under 14 Boys on Saturday from 8 am until 5 pm using courts 1 to 4`, `start at 9 instead`, and `rest 60 minutes`. Ambiguous draw names/dates require selection. The optional AI interpreter receives the current request and minimal event structure, not participant records or retained transcripts. Never include sensitive information in voice or AI requests.

## Local setup and operation

The code uses the existing Laravel dependencies and Gemini configuration; no dependency installation is required. No credentials are copied from TeamSheet. The permanent provider key remains on the server. HTTPS verification is always enabled.

Required additive migrations, individually reviewed and listed in `deploy.config`:

- `database/migrations/2026_11_01_120000_create_event_assistant_tables.php`
- `database/migrations/2026_11_01_120100_create_event_assistant_timing_observations.php`

Run only these exact paths against the intended local database after verifying its environment. Adding paths to the deployment configuration does not authorize production migration execution. A deployment must separately include the tracked CSS/JS assets, clear/rebuild route/view/config caches appropriately, and keep the Laravel scheduler operating.

`EVENT_ASSISTANT_ENABLED=false` disables assistant routes/UI. `EVENT_ASSISTANT_MODE=local_only` blocks voice and AI even if a browser requests them. Default typed operations work locally without a provider call. **Use AI** opts in for that message; **Speak** opts in to transcription separately. Request limits apply to messages and voice; API routes are also throttled.

Conversations are scoped to actor/event, expire after 30 days and are physically pruned by the hourly bounded `assistant:prune --apply --limit=1000` schedule. `php artisan assistant:prune` previews one deletion batch. Confirmation proposals expire after 15 minutes and store hashed tokens and exact payload fingerprints. No secrets enter browser data. Raw audio is used only for the request and is not persisted by the assistant.

## Learning and measurement

Feedback categories and the improvement queue are deterministic monitoring, not automatic model training. An accepted schedule alone is not a quality label. Explicit preferences affect only the current admin/event and are never permission rules.

The timing panel loads completed matches for one selected draw and captures explicitly verified actual start/finish times. Interrupted, retired and walkover observations are excluded from duration suggestions. Timed matches keep their configured playing time. Suggestions never silently update a schedule. Fewer than 20 comparable verified observations use a configured fallback. At sufficient sample size the pilot returns an empirical 80th-percentile duration rounded to five minutes; this is a baseline, not a proven trained predictor.

The portable evaluator is:

```text
php packages/assistant-foundation/bin/evaluate-durations.php reviewed-observations.json
```

Input is an array of reviewed records with `event_id`, `event_date` (ISO), normalized `format`, normalized `age_group`, `actual_minutes`, `verified`, `interrupted`, `walkover`, and `retired`. Export/review data locally and omit names, contacts, recordings and transcripts. The evaluator holds out whole later events and reports mean absolute error, default baseline error and underbooking rate. It requires at least three events, never enables a model automatically, and must be supplemented by representative tournament evaluation. Category identifiers in the live pilot are event-scoped; a reviewed cross-event taxonomy is needed before comparing cohorts across tournaments. No historical duration or model-improvement claims are made without that evidence.

## Shared foundation and TeamSheet

`packages/assistant-foundation` is the local versioned extraction boundary for provider contracts, exact-proposal fingerprints, feedback categories and duration baselines/evaluation. It owns no application data, credentials or permissions. Cape Tennis loads it directly; its Composer metadata supports later extraction into a private package repository. TeamSheet is not changed by this implementation. Its adoption and a remote shared repository require their own reviewed integration; neither has been deployed or published here.

## Verification

Run focused assistant tests with isolated database/runtime settings. `tests/Feature/EventAssistantPanelTest.php` can export current rendered Blade when `CT_ASSISTANT_QA_HTML=1`. `tests/Browser/event-assistant.cjs` loads that fixture and real assets with in-memory endpoint doubles at phone, tablet and desktop widths. It verifies the UI contract and voice failure recovery, not a real microphone, a paid provider or deployed persistence. Backend feature tests cover canonical persistence separately.

The required deployment configuration check is `php vendor/bin/phpunit tests/Unit/DeploymentConfigTest.php --fail-on-skipped`. Route registration, Blade compilation, PHP/JS syntax and `git diff --check` complete the local checks. Do not infer production readiness or delivery from these tests.
