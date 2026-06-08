# JS API Error Propagation — Audit Report

## Status: VERIFIED ✅

No JS test runner is configured in this project (package.json has no jest/vitest/mocha).
The following is the result of a code audit of the three relevant files.

---

## D1. `public/assets/js/admin/core/api.js` — Error rejection shape

**Before this session:** rejection was `{ status, message, xhr }`
**After this session:** rejection is `{ status, message, xhr, body }`

The `body` key is set to `xhr.responseJSON || {}`, giving callers access
to all server-side JSON fields (e.g. `confirm`, `success`, custom keys).

Relevant line (api.js):
```js
reject({ status: xhr.status, message: msg, xhr: xhr, body: xhr.responseJSON || {} });
```

---

## D2. `public/assets/js/admin/roundrobin/groups.js` — confirm check

Checks `err.body.confirm` (not the old `err.confirm`):

```js
if (err && err.status === 422 && err.body && err.body.confirm) {
    AdminConfirm.destructive(err.message || 'Results already exist. Regenerate anyway?')
        .then(function (confirmed) {
            if (confirmed) { regenerateFixtures(true); }
        });
}
```

✅ No old `err.confirm` checks remain.

---

## D3. `public/assets/js/admin/roundrobin/brackets.js` — 422 handling

Checks `err.status === 422` and shows a force-override confirm dialog:

```js
if (err && err.status === 422) {
    AdminConfirm.destructive(msg + '\n\nGenerate anyway with incomplete scores?')
        .then(function (ok) {
            if (ok) { generateMainBracket(true); }
            else { $('#main-bracket-wrapper').html('<div class="alert alert-warning">...'); }
        });
}
```

✅ Uses `err.message` (populated from `body.message` via the api.js rejection).
✅ Force re-call passes `{ force: 1 }` to backend.
✅ No old `err.confirm` checks in brackets.js.

---

## D4. Old `err.confirm` pattern

Search result: `err.confirm` does NOT appear anywhere in the codebase after
the update. Only `err.body.confirm` is used in groups.js.

---

## Manual Browser Test Checklist

Test at: `/backend/draw/roundrobin/{draw}`

Use a draw that has RR fixtures but NO scores entered yet.

### Status Bar
- [ ] Open the RR draw page
- [ ] Confirm the status bar appears above the tabs
- [ ] Confirm "Groups" badge is green if players are assigned, grey if not
- [ ] Confirm "Fixtures" badge is green if fixtures exist, grey if not
- [ ] Confirm "RR 0%" badge is shown in grey
- [ ] Confirm warnings list mentions unscored matches

### Bracket generation — incomplete RR
- [ ] Click "Generate Main Bracket"
- [ ] Confirm a 422 toast/alert appears with a clear message about unscored matches
- [ ] Confirm a second "Generate anyway?" confirm dialog appears
- [ ] Click Cancel
- [ ] Confirm no bracket is generated (bracket tab remains empty)
- [ ] Click "Generate Main Bracket" again
- [ ] Click "Generate anyway?" (force)
- [ ] Confirm bracket generation proceeds (may succeed or fail depending on draw data)

### Partial results — group regeneration guard
- [ ] Enter scores for at least one RR match
- [ ] Confirm status bar updates: "RR X%" badge turns yellow/warning
- [ ] Go to Players & Groups tab
- [ ] Click "Regenerate Fixtures"
- [ ] Confirm the first confirm dialog appears ("Delete existing fixtures?")
- [ ] Confirm the draw, then observe a SECOND confirm about existing results
- [ ] Cancel — confirm no regeneration happens and existing scores are preserved
- [ ] Click Regenerate again, confirm both dialogs, and confirm it works (force)
- [ ] Confirm existing scores are cleared after force regeneration

### All results complete
- [ ] Enter scores for ALL RR matches
- [ ] Confirm status bar: "RR 100%" in green, "Standings" in green
- [ ] Confirm warnings include "Standings are complete — playoffs can now be generated"
- [ ] Click "Generate Main Bracket"
- [ ] Confirm no 422 appears (generation proceeds directly)
- [ ] Confirm bracket appears in the Brackets tab

### Standings consistency
- [ ] Open Standings tab
- [ ] Confirm standings match results visible in the Matrix tab
- [ ] Open Order of Play tab
- [ ] Confirm fixture records match what is in the Matrix tab (same fixture IDs)
- [ ] Open Brackets tab after generation
- [ ] Confirm seeding order matches standings (top of Group A = Seed 1, etc.)

### Locked draw guard
- [ ] Lock the draw (Status Badges → Lock)
- [ ] Try "Generate Main Bracket" with force=1 (via browser dev tools or UI)
- [ ] Confirm 403 is returned (not 422, not 200)
- [ ] Try "Regenerate Fixtures" with force=1
- [ ] Confirm 403 is returned

### Published draw guard
- [ ] Publish the draw
- [ ] Try "Generate Main Bracket"
- [ ] Confirm 403 is returned even with force=1
