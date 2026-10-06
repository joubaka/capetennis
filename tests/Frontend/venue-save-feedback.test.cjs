const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/js/pages/headOffice.js', 'utf8');
const start = source.indexOf('  function venueSaveError(xhr)');
const end = source.indexOf('  function showVenueError', start);
const message = new Function(`${source.slice(start, end)}; return venueSaveError;`)();

test('venue validation explains every blocking issue without duplicate messages', () => {
  assert.equal(message({status:422, responseJSON:{message:'Invalid data', errors:{
    age_group_default:['Girls draw has saved bookings. Review the venue schedule.'],
    'venue_id.1':['Select a venue.', 'Select a venue.']
  }}}), 'Girls draw has saved bookings. Review the venue schedule. Select a venue.');
});

test('unexpected server errors explain recovery without exposing exception details', () => {
  const result = message({status:500, responseJSON:{message:'SQLSTATE secret internal details'}});
  assert.match(result, /HTTP 500/);
  assert.match(result, /check this draw and its age-group defaults before retrying/);
  assert.doesNotMatch(result, /SQLSTATE|secret/);
});

test('session, permission, missing draw and connection failures have actionable feedback', () => {
  assert.match(message({status:419}), /session has expired/);
  assert.match(message({status:403}), /locked or published/);
  assert.match(message({status:404}), /no longer available/);
  assert.match(message({status:0}), /check the saved venues before retrying/);
});
