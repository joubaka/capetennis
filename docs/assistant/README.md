# Cape Tennis tournament assistant

The assistant is a **super-user-only pilot until testing and explicit approval for wider access**. The event workspace header exposes **Ask assistant** only to the `super-user` role, with existing `event.manage` checks retained. All assistant endpoints, including voice, history, feedback, generation and timing tools, enforce this role on the server. There is no date-based or automatic rollout, and the enable flag cannot grant access to other roles. Widening access requires a separate reviewed code change after approval.

It supports typed requests, optional voice transcription, conversational corrections, reviewed schedule proposals, separate order-of-play publication, empty individual draw creation, feedback and explicit timing preferences.

## Use

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
