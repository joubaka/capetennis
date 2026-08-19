# JTA Results API v1

Cape Tennis is the authoritative source for this read-only integration. JTA may resolve an already-known player and poll that player's completed tournament results and official series rankings. The API never creates players and does not expose dates of birth, contact details, guardian details, account data, identity hashes, or ranking workflow audit data.

## Authentication

Every endpoint requires an HTTPS bearer request in production, a Laravel Sanctum personal access token, and the dedicated `jta-results:read` ability. This privileged ability is intentionally absent from Jetstream's user-facing token choices.

A Super Admin issues or rotates the service token from the application server:

```bash
php artisan jta:issue-results-token --user=owner@example.org --name=jta-production --expires-days=90
```

The selected owner must have the `super-user` role. Issuing the same name revokes the old token before creating the replacement. The command prints the plain token once; store it in JTA's secret store and do not put it in source control, logs, tickets, or screenshots. Rotate before `expires_at`, update JTA, confirm polling, and revoke/rotate immediately if exposed.

## Endpoints

### `GET /api/v1/integrations/jta/health`

Returns only the API version, service status, and generation timestamp.

```json
{"api_version":"v1","status":"ok","generated_at":"2026-08-19T10:00:00+02:00"}
```

### `POST /api/v1/integrations/jta/players/resolve`

POST keeps the date of birth out of query strings and normal access logs.

```bash
curl --fail --request POST 'https://capetennis.example/api/v1/integrations/jta/players/resolve' \
  --header 'Authorization: Bearer YOUR_TOKEN' \
  --header 'Content-Type: application/json' \
  --data '{"first_name":"John","last_name":"Smith","date_of_birth":"2012-04-15"}'
```

An exact canonical name/surname/date-of-birth match returns:

```json
{"data":{"cape_tennis_player_id":123,"display_name":"John Smith","identity_match":"exact"}}
```

No match returns 404. Multiple historical rows matching the same canonical identity return 409 for manual Cape Tennis review. Resolution never creates or changes a player.

### `GET /api/v1/integrations/jta/players/{player}/results`

Query parameters:

- `updated_since`: ISO-8601 datetime including timezone, for incremental polling.
- `per_page`: 1-100; default 50.
- `page`: deterministic page pagination.
- `cursor`: cursor pagination; start with an empty `cursor=` value, then follow the returned `links.next` URL.
- `full_snapshot`: boolean. When true, `updated_since` is ignored and a complete paginated reconciliation snapshot is returned.

```bash
curl --fail 'https://capetennis.example/api/v1/integrations/jta/players/123/results?updated_since=2026-08-18T00:00:00%2B02:00&per_page=50' \
  --header 'Authorization: Bearer YOUR_TOKEN'
```

Each `data` item contains the stable `ct-fixture-{id}` source match ID, fixture ID, semantic SHA-256 source version, correction-aware source timestamp, public event/category/draw metadata, original side ordering, fixture-authoritative winner, and ordered set scores. `source_version` changes when exported event metadata, scheduling, participants, winner, or scores change. Score corrections retain the source match ID.

Scheduled Order of Play time is used when present (`scheduled_time`). Otherwise the event start date at midnight is returned (`event_start`). Scores are never reversed to put the requested player first.

### `GET /api/v1/integrations/jta/players/{player}/event-results`

Uses the same `updated_since`, `per_page`, `page`, `cursor`, and `full_snapshot` parameters as the match-results endpoint. It returns published event finishing positions even when Cape Tennis did not use its internal draw and fixture system.

```json
{
  "source": "cape_tennis",
  "result_type": "placement",
  "source_result_id": "ct-placement-237-45-12345",
  "source_version": "semantic sha256 hash",
  "source_updated_at": "2026-08-19T10:00:00+02:00",
  "event": {
    "id": 237,
    "name": "Cavaliers Junior Strand Tournament 2026",
    "start_date": "2026-08-08",
    "end_date": "2026-08-10"
  },
  "category": {"id": 45, "name": "Boys U14"},
  "registration_id": 12345,
  "players": [
    {"cape_tennis_player_id": 123, "display_name": "John Smith"}
  ],
  "placement_type": "singles",
  "position": 3,
  "field_size": 24
}
```

The stable source ID is composed from event, category, and registration rather than the `category_results` row ID because Cape Tennis replaces those rows when a published position is corrected. A correction therefore keeps its source ID and receives a new `source_version`.

### `GET /api/v1/integrations/jta/players/{player}/series-rankings`

Uses the same incremental and pagination parameters as the two result endpoints. It returns only official rankings from the canonical publication lifecycle: the series leaderboard must be public, the ranking row must have `published` status, and it must belong to a publication run.

```json
{
  "source": "cape_tennis",
  "result_type": "series_ranking",
  "source_result_id": "ct-series-ranking-12-45-123",
  "source_version": "semantic sha256 hash",
  "source_updated_at": "2026-08-19T10:00:00+02:00",
  "series": {"id": 12, "name": "Cape Junior Series", "year": 2026},
  "ranking_list_id": 77,
  "category": {"id": 45, "name": "Boys U14"},
  "player": {"cape_tennis_player_id": 123, "display_name": "John Smith"},
  "rank_position": 2,
  "total_points": 175,
  "published_at": "2026-08-18T14:30:00+02:00",
  "event_legs": [
    {"event_id": 237, "points": 100, "position": 1, "status": "counted", "synthetic": false},
    {"event_id": 244, "points": 25, "position": 4, "status": "dropped", "synthetic": false}
  ]
}
```

The stable ranking ID is composed from series, category, and player. An official correction or later publication changes `source_version` without creating another logical JTA ranking. Internal `run_id`, raw `meta_json`, reviewer/publisher identities, and draft history are not exposed.

## Export rules and limitations

V1 exports published individual-draw fixtures only when:

- status is completed (`1`) or legacy scored/advanced (`3`);
- both non-zero registration IDs exist and each side has players;
- both sides are one-player singles or two-player registration-based doubles;
- every stored set has both side scores;
- the fixture winner equals side 1 or side 2; and
- both the event and draw are published.

True byes, double-byes, placeholders, unscored completed rows, in-progress/partial scores, malformed sides, unpublished records, and unrelated players are excluded. `fixture.winner_registration` is authoritative.

Placement results come from `category_results` and are exported only when both `events.published` and `events.results_published` are true. Positions must be positive and the registration must contain one player or a two-player doubles partnership. Historical duplicate rows are collapsed to the newest row for the same event/category/registration. Placement records never invent opponents, matches, or set scores.

An event may legitimately appear in both feeds: the placement endpoint describes where the player finished, while the match endpoint describes recorded player-versus-player matches. JTA must treat a placement as an event achievement, not as another match. A placement-only event means individual match scores were not recorded in Cape Tennis; it does not mean the player played no matches.

Team/interpro results are not supported in v1. They use separate team fixture/result structures and have not been privacy- and identity-mapped to the registration-based contract. JTA must not treat their absence as confirmation that no team match occurred.

Series rankings come only from canonical `series_rankings` rows. Calculated, reviewed, archived, legacy rows without a publication run, rankings in a hidden series, and rankings belonging to other players are excluded. `event_legs` mirrors the public leaderboard's counted/dropped score presentation; the API never recalculates rankings during a request.

## Polling and reconciliation

Poll all three feeds incrementally every 30-60 minutes using separate last-successful `source_updated_at` checkpoints and a small overlap window. Deduplicate matches by `source_match_id`, and placements and rankings by `source_result_id`; apply only changed `source_version` values. Run `full_snapshot=true` nightly for all feeds and paginate until `links.next` is null. A record missing from a later snapshot must be flagged for review, never automatically hard-deleted from player history.

The named limiter defaults to 60 requests per minute per service token. Handle 429 with exponential backoff and jitter. Retry transient 5xx responses; do not retry 401/403 until credentials are corrected.

## Privacy and audit

Responses expose only Cape Tennis player ID and display name for participants. The dedicated integration audit records token ID/name, endpoint, linked player ID, status, and timestamp. It deliberately omits date of birth, request bodies, response bodies, bearer values, and match payloads.

## Production configuration

```dotenv
JTA_API_RATE_PER_MINUTE=60
JTA_TOKEN_EXPIRATION_DAYS=90
JTA_API_REQUIRE_HTTPS=true
```

`APP_ENV=production`, a correct HTTPS `APP_URL`, trusted reverse-proxy forwarding, the normal database, and the canonical audit migration must also be configured. No bearer token belongs in `.env.example` or Cape Tennis source.

## Deployment and rollback

1. Back up the database and deploy the reviewed application commit.
2. Run only `2026_08_19_130000_add_results_published_to_events.php` and `2026_08_19_130100_add_jta_result_export_indexes.php`. The first adds the existing publication flag on clean installations and is a no-op where the column already exists. The second adds bounded player/registration placement and player/ranking lookups. Ensure the existing `personal_access_tokens` and canonical `audit_events` migrations are also applied.
3. Cache configuration/routes as used by the production runbook, then verify the five routes with `php artisan route:list --path=api/v1/integrations/jta`.
4. Issue the expiring token, store it in JTA, call health, resolve a controlled player, and compare small match, placement, and series-ranking samples with Cape Tennis.
5. Enable polling and monitor 401/403/429/5xx audit outcomes without logging payloads.

To roll back, stop JTA polling first, delete/rotate the named JTA token, deploy the previous application commit, and clear/rebuild Laravel caches. Do not drop `results_published` during an application-only rollback because it is pre-existing production data used by the public event results page. Preserve audit history.
