# Registration terms and refund guidance

Self-service registration requires explicit acceptance of the registration terms
and refund guidance before confirming a free entry, handing payment to PayFast,
or completing a wallet payment. The legacy `require_terms` setting must not
silently pre-accept this requirement. Code of Conduct requirements remain
independent. Provider callbacks and paid-order returns do not request consent.

`frontend.partials.registration-terms` preserves and displays the saved
`terms_conditions_content` and supplements it with the shared
`registration-refund-terms` guidance. Do not overwrite site settings to add this
guidance. Pass the exact event and the `team` funding context for team checkouts;
individual hybrid bank refunds and team refunds use different fee bases.

Keep guidance aligned with `Event::withdrawalCloseAt()`, the event-start
self-service cutoff, `SiteSetting::calculateWithdrawalFee()`, the individual
refund controller and `TeamRefundCalculator`. Withdrawal is separate from a
refund request; pending is separate from completed. A no-refund withdrawal is
an operational record and does not itself return money.

Acceptance checks belong before the relevant registration or payment mutation,
after the existing actor/resource access checks. They must not weaken payer
ownership, sponsored registration eligibility, signed recovery links, payment
verification or idempotency. Administrative entry operations retain their
separate authorization.

Focused coverage includes `RegistrationRefundTermsTest`,
`RegistrationWalletCheckoutTest`, `TeamCheckoutRenderingTest` and
`RegistrationPaymentRecoveryTest`. Tests and local code do not prove deployment.
