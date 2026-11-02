# Background assistant capability improvement

When an assistant task is not understood or supported, the opted-in user's single instruction can be recorded for background review. The workflow is **note → review → bounded local implementation → regression tests and independent review → separately approved deployment**. It does not execute the failed task, train a model automatically, promise a future fix, or grant the receiver permission to edit remote code.

## Standing opt-in and exact data boundary

Two independent settings are off/empty by default:

```dotenv
CAPESTER_AUTO_HANDOFF_ENABLED=false
CAPESTER_AUTO_HANDOFF_USER_IDS=
```

An operator may enable the feature only for users who have explicitly authorized this standing forwarding behaviour, using an exact comma-separated user ID allowlist. The normal Capester queue must also be enabled. Another Super User is not automatically opted in. This implementation changes no actual `.env` or production configuration.

Eligible structured outcomes are unsupported requests, unsupported scheduling constraints, category-copy grammar failures and category references with no accessible match. Ordinary missing dates, setup questions, ambiguous event/draw choices and rejected financial/destructive business actions do not automatically create notes. Marking a response **Needs improvement** can also create a note, including for a supported response that did not satisfy the user.

The note contains a fixed reason code, event/message references and **at most 1,400 characters of the one linked user instruction**. Each reply records the exact instruction message ID when it is created; concurrent messages cannot cause a “latest message” lookup to forward the wrong instruction. Draft bodies/subjects, full conversation history, provider response bodies, feedback comments, recordings, attachments and participant datasets are not copied. The one instruction itself can contain facts or personal data the user typed; opting in is not an assertion that all such information is anonymous. Keep sensitive material out of instructions.

Credential-like input (for example bearer credentials, password/API-key/merchant-key assignments, private-key markers and some provider-key patterns) withholds the whole instruction and records metadata only. This is a conservative heuristic, **not a guarantee of perfect secret detection or redaction**. Legacy replies without an explicit instruction link also produce metadata-only notes rather than inferring a transcript.

One deterministic submission UUID per assistant reply is reused across automatic capture and repeated feedback. The queued summary does not depend on feedback text or the later capture trigger. Notes are actor/event owned and use the existing bounded queue, audit redaction, leases, receiver and inert private inbox. Queue-full, unavailable and disabled states do not break the assistant reply or feedback and are reported without falsely claiming a saved note. A successful task is not automatically queued unless marked Needs improvement.

## Category-copy understanding

Both word orders are supported locally:

- `Copy categories from Panarottis/Spur Leg 1 to Legs 2 and 3.`
- `Can you copy the spur and Panarottis leg one categories to the spur and Panarottis leg two as well.`

Spoken leg numbers one through ten and sponsor ordering/conjunction punctuation are normalized. Terminal politeness can be removed; additional instructions are never silently discarded. The entire destination is resolved before trying destination lists, so sponsor “and” is not treated as another event.

Lookups are bounded and limited to accessible events. A sponsor-plus-leg shorthand can match additional event-name tokens only when unique; significant supplied year/place tokens are retained. Inferred destinations retain the source family/place and year (including known event dates), so the assistant does not fall back to a different year or city. Explicit full qualified names or event IDs can request an intentional cross-event/year copy. Ambiguous matches show a bounded choice of accessible names/IDs and produce no preview. Typos such as `Panaratis` are not silently guessed; an unresolved opted-in task may instead be recorded for future capability work.

The existing preview still shows the exact source/destinations, category add/skip decisions, fee overrides/effective fees and ordering. Only reviewed confirmation applies a copy; all stale-state, authorization, idempotency and unchanged-data checks remain in the canonical category-copy workflow. This change does not copy any live categories by itself.

## Background collection and release boundary

Background collection/review is configured separately from the application feature. It requires the receiving computer, Codex app and any separately authorized scheduler to remain available; the note can stay pending while they are offline. The receiver still stores support text as inert data and never passes it as a Codex prompt. A separately authorized reviewer may inspect the protected inbox and work on a tested capability. Scheduling a review does not make arbitrary instructions safe, authorize production fixes or guarantee a completion time.

Verify locally with the isolated `phpunit.capester.xml` suite. A deployed feature, active actor opt-in, collected note, implemented fix, passing tests, approved deployment and live verification are separate states. Record reusable verified fixes in the repair library; a saved note is not yet verified repair knowledge.
