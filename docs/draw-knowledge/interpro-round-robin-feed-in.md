# Interpro round robin and fixed feed-in

The explicit **Interpro trials — round robin → feed-in** setup choice uses the
shared individual draw workspace. It stores `round_robin_playoffs` with preset
`interpro_rr_feed_in`; it does not convert existing draws automatically.

There must be four groups named A–D, each containing three or four players.
Complete the full group programme and resolve tied finishing positions before
reviewing and confirming all four groups. Fourth-place finishers have no playoffs.

## Fixed routes

- A1–D1 and B1–C1 are championship semifinals; their winners contest 1st/2nd.
- A2–D3 and B2–C3 produce one crossover survivor; C2–B3 and D2–A3 produce another.
- Those survivors meet, respectively, the loser of B1–C1 and the loser of A1–D1.
  Winners contest 3rd/4th; losers contest 5th/6th.
- The two players eliminated immediately before the feed-in contest 7th/8th.
- Initial crossover losers play two semifinals, then 9th/10th and 11th/12th.

The declarative template in `InterproRoundRobinFormat::template()` is the owning
topology. Its 18 playoff identities remain stable after preparation, including
before qualifiers are known. A full 16-player field has 24 RR matches and up to
18 playoff matches; carried results reduce the matches actually played.

## First result stands

The first decisive meeting in this exact draw determines a repeated pairing.
The RR source takes precedence; otherwise the earlier playoff topology source
takes precedence. A drawn first meeting cannot silently become a win.

A carry is a progression reference to a genuine original result, never a second
played match, copied set rows or synthetic walkover. Store the source ID and
content fingerprint inside the selected preset's configuration. Canonical
outcomes verify draw identity, unordered participants, earlier topology and the
original fingerprint. Display scores in the current slot orientation and label
the original match. A changed or unavailable source requires review.

Confirming progression requires a decisive result in every RR match, including
when timed scoring is selected. Resolve drawn original matches during the group
stage before confirmation; a first draw cannot decide a later knockout winner.

Resolve both winner and loser paths in template order, independent of the order
in which scores arrive. Carried pairings cannot be scored again or booked on a
court. Release their working booking when they resolve, retaining the audit and
immutable published snapshot; public schedule projection omits the obsolete
booking. No carried fixture set rows means no duplicate played-match or rating
evidence.

Withdrawal reconciliation retains completed and carried history. A future real
match follows the canonical withdrawal loss; a branch with no available player
passes an explicit absence forward, allowing its next opponent to advance
without inventing a played match. Generic bye inference must not interpret a
pending feed-in source as an absent opponent.

## Current correction boundary

Confirmed RR results, completed playoff results and carried targets/originals
reject direct score replacement or clearing. The existing RR tournament-recovery
operation also rejects changes to confirmed trial sources: it does not yet reset
and reconstruct this special graph safely. This is an explicit limitation,
not an instruction to bypass guards or edit records directly. A future correction
workflow must review all feeder and carry dependencies and preserve publication,
schedule and audit history before changing this boundary.

Use the shared event scheduler; legacy plate generation and older timetable
writers cannot replace this format. Setup changes retain normal authorization,
event isolation, unpublication, result protection and explicit-reset safeguards.

## Verification sources

- `tests/Feature/Draw/InterproRoundRobinFormatTest.php` covers historical field
  sizes, stable topology, reviewed progression, carried outcomes/evidence counts,
  all 12 positions, schedule exclusion, score protection, publication and
  withdrawal handling.
- Adjacent regressions: `RoundRobinProgressTest`,
  `AutomaticPlayoffPreparationTest`, `InterproDrawGenerationPreservationTest`.

These are source/test references, not production deployment or browser evidence.
