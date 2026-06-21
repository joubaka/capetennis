# Round Robin Scoring on Published Draws - Fix Summary

## Problem
Users were unable to enter round robin scores after the draw was published, receiving the error: "Draw is already published."

## Root Cause
The system was blocking ALL score entry on published draws, regardless of draw type. However, for round robin tournaments, the draw needs to be published so players can see their match schedule, but scores should still be enterable as matches are played.

## Solution
Modified the guard logic to allow scoring on published draws **only for round robin fixtures** (where `fixture->stage === 'RR'`). Bracket/playoff fixtures on published draws remain blocked as before.

## Files Changed

### 1. `app/Domain/Draws/Guards/DrawGuard.php`
- **Method**: `requireScoreable()`
- **Change**: Added check to allow scoring on published draws for round robin fixtures
- **Line**: Added condition `$isRoundRobin = $fixture->stage === 'RR';`

### 2. `app/Http/Controllers/Backend/RoundRobinController.php`
- **Method**: `deleteScore()`
- **Change**: Added check to allow deleting scores on published draws for round robin fixtures
- **Line**: Added condition to only block published draws for non-RR fixtures

### 3. `app/Http/Controllers/Api/DrawApiController.php`
- **Method**: `saveScore()` and `deleteScore()`
- **Change**: Added checks to allow score operations on published draws for round robin fixtures
- **Lines**: Added RR check before blocking published draws

### 4. Test Files Updated
- `tests/Feature/Draw/RRHardeningTest.php`
  - Updated: `test_published_draw_allows_score_save_for_round_robin()`
  - Updated: `test_published_draw_allows_score_delete_for_round_robin()`

- `tests/Feature/Draw/DrawApiControllerDeleteScoreTest.php`
  - Updated: `test_published_draw_allows_api_score_delete_for_round_robin()`

## Behavior After Fix

### Round Robin (stage = 'RR')
- ✅ **Published + Unlocked**: Can enter/delete scores
- ❌ **Locked** (published or not): Cannot enter/delete scores
- ❌ **Verified fixtures**: Cannot modify scores

### Bracket/Playoff (stage = 'MAIN', 'PLATE', etc.)
- ❌ **Published**: Cannot enter/delete scores (unchanged)
- ❌ **Locked**: Cannot enter/delete scores (unchanged)

## Testing
All tests pass:
- ✅ RRHardeningTest (23 tests)
- ✅ DrawApiControllerDeleteScoreTest (3 tests)
- ✅ BracketHardeningTest (13 tests) - confirms brackets still blocked
- ✅ DrawLockHardeningTest (15 tests)

## Impact
- Round robin tournaments can now be published (so players see their schedule) while still allowing score entry
- Bracket/playoff draws maintain existing security - no scoring on published draws
- Locked draws of any type remain fully protected from mutations
