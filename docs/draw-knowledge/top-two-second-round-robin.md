# Top-two and bottom-two second round robins

This is a separate Round robin → round robin choice for exactly eight players
in two opening groups of four. Preserve the existing equal-position option and
the championship-playoffs/carried-lower-group alternative.

The top two finishers from each opening group form a four-player championship
round robin for positions 1–4. The bottom two from each opening group form a
four-player consolation round robin for positions 5–8. Finishing positions mean
confirmed opening-stage standings, never starting seeds or rating order.

Players who advance together carry their mutual opening-stage result into the
destination group. Do not replay that match. Each destination group counts two
carried comparisons and four new cross-group matches. The whole competition has
twenty actual matches: twelve opening matches and eight new second-stage matches,
five per player. Carried comparisons reference original fixture and score rows;
never duplicate them or manufacture rating evidence. Scoring remains an explicit
canonical draw setting; selecting this format does not impose a scoring preset
on unrelated competitions. Ordinary equal-position second groups retain their
existing fresh-score behaviour.

Prepare every new second-stage fixture with its exact source-group and finishing-
position tuple. A later group contains two positions from each source group, so
one qualifying position on the destination group cannot describe all its slots.
Keep actual participants unresolved until authorized review and confirmation of
complete, untied, eligible opening standings. Fill existing fixture identities in
place and preserve private/public bookings. Repeated preparation or confirmation
must not duplicate groups, fixtures, memberships or scores. Before confirmation,
unresolved later fixtures cannot be scored; after confirmation, opening results
remain protected by the existing staged-draw lifecycle.

Scheduling must count four possible players in each later group, retain the
opening-stage completion barrier and check possible-player conflicts and rest
using the exact qualification sources; carried comparisons need no new booking.
Final positions come from the championship
and consolation standings independently, and remain pending until the relevant
group is complete and its order resolved.

Existing draws require an explicitly reviewed reset or rebuild before choosing
this format when their protected state requires one. Selecting a new format is
not permission to remove results, alter a different draw, publish, schedule or
send messages. Local implementation and test evidence do not establish deployment
or completion of any live event's draw changes.

Owning sources: `TopBottomRoundRobinService`, `TwoStageRoundRobinService`, `DrawSetupController`,
`RoundRobinGenerationService`, `DrawFinalPlacementService`,
`RoundRobinGroupResultService`, and `EventVenueScheduleService`.

Focused regression: `tests/Feature/Draw/TopBottomRoundRobinTest.php`. Existing
two-stage and hybrid regressions protect their separate behaviour. Run checks
against an isolated test database and report their actual results separately
from implementation, browser verification and production availability.
