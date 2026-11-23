# Player pathway and hosted tournament levels

## Presentation

The pathway keeps five familiar options visible in two groups: Red and Orange
ball development, then Wilson B, Wilson A and external TSA opportunities within
Green/Yellow guidance. This is an exploration map, not a completion ladder.
Exploring an option does not move the player's latest-saved-stage marker or
change their assessed recommendation. Never mark earlier stages complete from a
later rating. Red, Orange and Green/Yellow scores remain separate.

Enhanced stage details move into one wide panel beneath the route. The original
native disclosures remain the fallback without JavaScript. My Tennis caches
clone DOM attributes without event listeners: `pathway-explore.js` tracks mounted
element identities with a `WeakSet`, restores moved content in clones, and binds
again on `my-tennis:mounted`. A copied dataset flag cannot establish a live mount.

The personal view consumes `PlayerPathwayService` presentation states. Show
pending dates/status beside saved values. Green/Yellow uses a guidance-band
graphic; Red/Orange targets use their own ball-level CTR and published event
count. Missing evidence is unavailable, not zero, and recorded moves do not
borrow the previous ball level's score.

The public introduction lives at `/ctr#pathway`, within the existing configured
Player Plus offer. Anonymous stage discovery reads published opportunities, not
player identities or private rating evidence. Examples are explicitly fictional.
Marketing availability and personal entitlement remain separate existing gates.

## Organiser classification

`events.pathway_level` is an optional event default. An event's
`category_events.pathway_level` overrides it; null inherits it. The association
owns the override, never the globally shared category. Hosted choices are `red`,
`orange`, `wilson-b` and `wilson-a`; TSA is an external opportunity, not a hosted
choice. Changing the event default retains explicit category overrides.

Event create/edit and the event Categories page expose these settings. Each
category displays its configured effective label and whether it inherits the
default. A configured level conflicting with known category/court ball evidence
receives a warning and is withheld from validated discovery.

`EventPathwayClassification::forCategory()` resolves the validated public stage.
`label()` describes configuration for administration only; public badges and
matching must not use it to bypass conflict checks. Explicit configuration is
discovery guidance and does not rewrite played-match ball evidence, recalibrate
ratings, publish an event or grant registration eligibility. Unknown legacy
events must not be assigned a level from their title alone.

The exact schema migration is
`database/migrations/2026_11_23_150000_add_pathway_levels_to_events_and_categories.php`.
It adds two nullable fields without backfilling existing classifications. Its
deployment allowlist is not permission to execute production migrations.

## Coach sharing

The sharing kit uses the generic public destination
`https://capetennis.co.za/ctr#pathway`. Its QR, vector poster and prepared
WhatsApp/copy message contain no player or checkout identifier. Sharing is
user-operated and never grants coaching or private-player access. The static
poster does not encode a price or promise sales availability.

Regenerate the QR and poster with `php scripts/generate-pathway-share-qr.php`,
using the existing BaconQrCode dependency. Review both generated assets visually
after changes. The QR has a four-module quiet zone and medium error correction.

## Verification

Use `PlayerPathwayTest`, `PlayerPathwayEventsTest`,
`EventPathwayClassificationTest`, the existing Plus marketing/preview tests and
`DeploymentConfigTest` for the affected behavior. Browser checks must include
phone and desktop layouts, keyboard exploration, independent current/explored
state, missing/pending evidence and generic share links. Test purchaser return
destinations without weakening verified-settlement, payer or entitlement checks.
Purchasing for a player never creates a private-access relationship.

`tests/Browser/pathway-experience.cjs` exercises the rendered fictional fixtures,
including full-width exploration, independent saved markers, cached-workspace
remounting, generic sharing and clipboard fallback. The existing recommendation
and loading checks remain relevant to the canonical event and stage-info flows.
