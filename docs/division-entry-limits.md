# Division entry limits

Caps apply to individual entry events, including the individual Masters and Trials workflows. Team roster limits retain their own configuration. The cap counts registration entries; the court helper is intended for singles divisions where one entry is one player.

Event managers can set an optional maximum on each division under **Event → Categories → Division entry cap**. Blank means unlimited. Select **Allocate courts** to open the calculator for that division, choose Red, Orange, Green or Yellow, and enter the number of normal courts. Red uses four mini-courts per normal court with four players on each: **16 players per normal court**. Orange, Green and Yellow use **four players per normal court**. The popup shows the calculation as you edit it. **Use cap** fills that division's maximum entries; **Save cap** then saves it. Allocate shared courts across divisions yourself; the helper does not reserve courts. Venue changes never change saved caps.

The public event Information section lists configured caps. Registration choices show remaining places and mark full divisions. These numbers are advisory; the server checks again under a category lock when starting a checkout or adding/moving an entry.

Paid active entries and unresolved order items occupy places, deduplicated by registration. Checkouts retain their places until the canonical payment cancellation or expiry workflow closes them; age alone does not discard a protected PayFast or EFT attempt. Withdrawn/deleted entries release their places. Pending category adjustments hold a destination place until completion or cancellation. Verified payment completion consumes the existing reservation rather than competing for another place.

Saving a lower cap preserves existing entries and payment commitments and shows the division as full. It never cancels an entry or changes a payment. Uncapped divisions retain existing behavior.

Owning sources: `DivisionCapacityService`, `EntryService`, `RegisterController`, Masters and Trials invitation services, `CategoryAdjustmentService`. Regression coverage: `DivisionEntryLimitTest`, `DivisionCapacityPublicDisplayTest`, category adjustment and registration workflow suites.
