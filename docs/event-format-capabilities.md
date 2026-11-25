# Event workflow capabilities

Competition family and admission workflow are separate. `isIndividual()` is
not permission to nominate or invite players: Masters, Cavaliers Trials and
camps reuse individual competition or payment behavior while retaining their
own admission rules. Interprovincial Trials has a dedicated workflow even when
its stored base family is team.

Ordinary individual events support entries, individual draws and results,
participant communications and the corresponding entry finances. They must
not expose nomination management, nominee communication audiences, invitation
operations or team clothing actions. Reject unsupported direct requests as
well as omitting their controls.

Keep the specialized workflows intact:

- Cavaliers Trials uses published category nominations and its private roster.
- Interprovincial Trials uses exact published nominees, sponsored registration,
  payer-scoped orders, linked-account decline and separate regional participation.
- Masters uses its qualified invitation queue and reserves with individual
  checkout. Determine Masters identity from its type, never its event name.
- Team events retain their roster, selection, team-player payments and applicable
  clothing workflow.
- Camps retain individual checkout with their dedicated approved nominees and
  optional invitation admission. Camp nominees are not generic event nominations.

Financial history follows actual source records, not merely the event's current
format. Preserve receipts, original payer, withdrawals, refund snapshots and
reversals when hiding irrelevant operational tools. Completed refunds reduce
realized net income; pending refunds remain a separate liability display.
Ordinary team refunds and Trials participation refunds must each appear once.
Showing historical evidence never grants permission for a new payment action.

Changing an established event's format requires protection against stranding
entries, orders, nominations, invitations, rosters, camp configuration or draws.
Do not resolve an incompatible transition by deleting these records. Dedicated
canonical workflow setup remains separate from a generic format selector.

This document grants no publication, sending, financial execution, migration or
deployment authority. See [event type preservation](architecture/event-type-template-parity.md)
and [camp workflow](practice-camp-planner.md) for existing specialized contracts.
