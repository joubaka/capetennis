# Account and player navigation

The shared account menu separates account management from player information.
`My Account` opens `backend.dashboard?manage_players=1#dashboard-account`.
The query opens the account details for administrators, whose dashboard also
contains event management. The anchor exists for ordinary accounts as well.

`My Wallet` opens `backend.dashboard?wallet_page=1#wallet-history`. That
dashboard reads the authenticated account's ledger-derived balance and paginated
transactions. `wallet.show` is an administrative browsing route; do not use it
as a general personal-wallet shortcut or broaden it to repair navigation.

`My Tennis` is the shared player workspace for normal profiles and Plus views.
The account dashboard's per-player shortcut passes the exact linked player ID
and `tab=profile`. The existing relationship and entitlement checks decide
whether private Plus views or locked previews are available. A shortcut never
creates a player link, grants Plus, or makes payer history accessible to another
account. Super User previews remain separate from membership.

The global pending bank-refund count and its controls are Super User only,
matching the destination route. Check both the view composer and desktop/mobile
controls; hiding a link alone does not protect the count. Mark the applicable
interactive controls with the shared Super Admin border.

Regression coverage: `AccountNavigationTest`, `MyTennisOverviewTest`,
`PlayerPlusFeaturePreviewTest`, and `PlayerPlusMembershipTest`. Browser checks
must use the actual rendered navbar and local styles, including keyboard
activation, Escape dismissal, small-screen scrolling and reachable account
destinations. Local checks do not establish deployment or live behavior.
