# Round-robin ties

Individual group standings rank match points first: two for a win, one for a
supported draw, and zero for a loss. The selected Interpro feed-in requires
decisive original results before progression.

When exactly two players have equal match points, their decisive head-to-head
winner ranks first, even if that player has a lower set or game percentage.
If no decisive direct result exists, the existing percentage fallback remains;
this does not relax the Interpro requirement for decisive results.

When three or more players share the same match points, compare sets won divided
by total sets first, then games won divided by total games. These totals include
all matches in the group, not only a mini-league between the tied players. Scores
starting at two-all include those starting games. Keep the existing 0.0001 ratio
tolerance; displayed rounded percentages are not used for comparison.

If exactly two players remain equal on both percentages within a larger points
cohort, their decisive head-to-head can separate them. A completely equal group
of three or more remains unresolved. Never apply pairwise head-to-head around a
three-player cycle or use presentation order, seeds, names or ratings to qualify
players. The full original points cohort must remain available when checking
later positions; slicing away the leader must not change the tie rule.

Walkovers award match points without invented sets or games. For retirements,
recorded games count and only completed sets count as set wins. Group review
confirms the calculated order; it does not provide an organiser override for a
genuine unresolved tie. Such a tie must remain blocked pending a separately
reviewed competition decision workflow. No manual override is implemented here.

The two-player-first policy is the user's explicit decision of 27 November 2026.
It replaces the previous ordinary full-set order that put percentages before
head-to-head in two-player points ties. Timed round-robin standings already used
this two-player-first order and retain it. Saved scores, fixtures, bookings and
confirmed playoff occupants are never rewritten by this calculation change;
existing confirmed competition graphs remain protected by their source checks.

Owning sources: `StandingsService` (ordering, labels and leader guard),
`TwoStageRoundRobinService::positionsResolved()` (all-position qualification),
and the timed path in `InterproDrawBuilder::standings()`.

Regression: `tests/Unit/RoundRobinHeadToHeadTieBreakTest.php` covers a head-to-head
winner with worse percentages, lower-position pairs, three-player sets/games
percentages, unresolved circular ties and unchanged full-cohort qualification.
`RoundRobinGroupResultTest` covers complete and unresolved group projections.
These references do not establish deployment or authorize live progression.
