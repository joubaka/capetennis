# Private player performance pilot - methodology v2

Super Admin's performance pilot links to a searchable directory of every player at backend/player-performance/players. Personal pages and backend profile cards remain GET-only and restricted to super-user. No score, ranking, result, player or publication record is written.

## Historical sources

Personal ratings consider all eligible published history, not just 12 months or 50 events. Target events stream in batches of 25 and fixtures in batches of 50. Scores and source totals aggregate full history; the view retains the latest 50 finish records and 50 match records per discipline, sorted by date, and reports full counts and all-history exclusion counts. The manual comparison retains its explicitly selected date window and 50-field limit.

Finishes follow the public results page's results_published gate. Latest saved corrections count. Saved ranked fields must have at least two distinct consecutive ranks 1..N, unique player identities and matching recorded event-category memberships. Extra unranked entrants do not erase published finishes. Current withdrawal/deletion does not erase recorded historical ranked membership. Unranked membership counts are disclosed; these entrants are not assumed absent. N means saved ranked finishes, not independently verified starters. Missing/duplicate ranked memberships, duplicate players, mixed disciplines, ambiguous field definitions and fields exceeding the 256-entry validation limit are excluded. Team finishing ranks are not individual ranks.

Head-to-head sources require publicly published events and draws. The finishing-table results_published flag is not required for already public draw scores. Individual matches require two distinct registrations with actual-event category membership, verified player counts, unique consecutive set numbers (including legacy zero-based numbering), valid scores and consistent aggregate/declared winners. Canonical ScoreValidationService enforces configured presets. Legacy draws without presets require credible terminal short/full/pro/tiebreak scores. Scored RR status 2 requires RR workflow and enough set wins to establish completion; legacy status 3 requires the recorded winner and enough configured set wins. Canonical completed status 1 remains authoritative for historical shortened matches without presets, provided the recorded sets are credible and consistent. Unplayed byes never count. Ambiguous/partial score evidence is excluded. Stale category-event references resolve read-only only when a unique same-category field in the actual event contains both registrations; no database repair occurs.

Team singles/doubles use publishedTeamTies and canonical TeamRubberResultService completed outcomes. Exact required player-profile identities on both sides, terminal scores and matching event/draw/tie are required. Immutable participant snapshots are checked through TeamParticipantHistoryService.matches against source team, event, category, region, profile and assigned sides. Without snapshots, exact recorded event-side roster membership is required. Legacy fixtures without a tie can resolve a side only through a unique team matching the fixture region, exact event category and every recorded side player; missing or ambiguous mappings remain excluded. Imported no-profile identities are excluded; names never establish identity.

Started, ongoing events can contribute published results. Their evidence date is the as-of date until the event ends. Future events are excluded.

## Scoring and comparison

Version 2 adds match contributions and all-history coverage; values can differ from version 1. Scores remain provisional and uncalibrated, not official UTR, opponent-strength ratings or selection policy.

Explicit A/B division/afdeling phrases and standalone terminal A/B tokens identify divisions. A plain main category counts as A only when uniquely paired with one matching B field in that event, without competing definitions. Unlabelled/unpaired categories use a separate Open cohort. Ambiguous labels are excluded. Age, gender, ball, region, Masters and team contexts remain distinct. Open and A/B cohorts are separate. Singles and doubles stay separate; the headline is the latest eligible singles cohort. No eligible data means Unrated.

Placement points = band minimum + band width * (N-position)/(N-1). A is 50-100, B is 0-50, Open is 0-100. For each event/cohort, placement points form an average finish component. Match win fraction maps into the same tier band as the match component. Both components contribute equally when available; otherwise the single component supplies the event score. Each event has one weight regardless of match count, avoiding doubled event weight from finishes plus matches.

Event scores average with a 180-day half-life, 0.5^(days since event/180), rounding once for display. Older history still contributes with lower weight. Eligible finish/match counts, opponents, outcomes and scores (player first) are shown. Qualification earns no bonus. Doubles describe partnership performance.

Validate familiar players and calibrate assumptions before publication or operational use. Local implementation does not grant publication authority.
