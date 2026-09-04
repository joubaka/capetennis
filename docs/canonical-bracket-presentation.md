# Canonical bracket presentation

`public/css/tennis-bracket.css` owns bracket line colour, stroke, font family and print rules.
`public/js/tennis-bracket.js` is the multi-diagram screen adapter. Draw engines still own
fixtures, feeder relationships, positioning, names, scores and authorization.

## Adding a bracket view

1. Include `draw.partials.bracket-assets` (already in commonMaster and Custom Monrad).
2. Mark the SVG root `ct-bracket-svg`, with a viewBox matching its native dimensions.
3. Emit structural edges as SVG lines or straight M/L/H/V paths marked `data-ct-edge`.
   Do not also outline the match rectangle or add a middle divider. Backgrounds and
   transparent score hit targets are separate from the structural edges.
4. Include `draw.partials.bracket-svg-style` inside AJAX/standalone SVG markup.
5. Let the document scroll vertically; horizontal overflow and zoom can remain.

The adapter reads each source element's screen transform (including nested groups,
viewBox, CSS zoom and scroll), aligns it to device pixels, merges coincident edges,
and paints one pointer-transparent layer above row backgrounds. It observes AJAX
replacement and zoom changes. It never changes source coordinates or draw data.
Unsupported curved paths are not converted. Icons and round-robin matrices are not
opted in. Team fixture tables remain tables.

Printing uses a separate unsnapped vector layer with the same line colour and a fixed
print stroke, not screen-pixel coordinates. Popup exports remove the screen layer and
embed the canonical CSS. Custom Monrad prints its complete board followed by its
existing fixture/placement reference tables.

Current adapters: Custom Monrad (draft, generated, public, demo), dynamic playoffs
(admin/public), Interpro, legacy main/plate/full SVGs, individual Monrad service,
seeded playoff and two-/four-box previews. Unused experimental renderers such as
SvgBracketRenderer and MonradFeedin are not alternate canonical implementations;
opt them in using this contract before exposing them as new draw screens.

Verification: `node --test tests/Unit/tennis-bracket*.test.cjs` and
`php artisan test --filter=CanonicalBracketPresentationTest`, plus focused draw suites.
Browser checks should include fractional browser zoom, internal zoom, multiple
diagrams, AJAX replacement, narrow viewport, scoring hit targets, and print preview.
