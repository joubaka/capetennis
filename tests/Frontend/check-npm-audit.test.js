'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const { evaluateAudit, run } = require('../../scripts/check-npm-audit');

const beforeExpiry = new Date('2026-10-03T00:00:00.000Z');

function report(vulnerabilities) {
  return { auditReportVersion: 2, vulnerabilities };
}

const bracesAdvisory = {
  url: 'https://github.com/advisories/GHSA-vfj7-8cjw-p6xm',
  title: 'braces has an uncontrolled resource consumption issue'
};

test('passes a clean npm audit report', () => {
  assert.deepEqual(evaluateAudit(report({}), beforeExpiry), {
    ok: true,
    allowed: [],
    unexpected: []
  });
});

test('allows only the named braces advisory and recursively derived parents', () => {
  const result = evaluateAudit(
    report({
      braces: { via: [bracesAdvisory] },
      micromatch: { via: ['braces'] },
      chokidar: { via: ['braces'] },
      'laravel-mix': { via: ['chokidar', 'micromatch'] }
    }),
    beforeExpiry
  );

  assert.equal(result.ok, true);
  assert.deepEqual(result.unexpected, []);
  assert.deepEqual(result.allowed.sort(), ['braces', 'chokidar', 'laravel-mix', 'micromatch']);
});

test('fails when a finding has any additional advisory root cause', () => {
  const result = evaluateAudit(
    report({
      braces: { via: [bracesAdvisory] },
      micromatch: {
        via: ['braces', { url: 'https://github.com/advisories/GHSA-xxxx-yyyy-zzzz', title: 'another issue' }]
      }
    }),
    beforeExpiry
  );

  assert.equal(result.ok, false);
  assert.deepEqual(result.unexpected, ['micromatch']);
});

test('fails closed for missing parents, cycles, malformed reports, and expiry', () => {
  assert.equal(evaluateAudit(report({ parent: { via: ['missing'] } }), beforeExpiry).ok, false);
  assert.equal(
    evaluateAudit(report({ first: { via: ['second'] }, second: { via: ['first'] } }), beforeExpiry).ok,
    false
  );
  assert.equal(evaluateAudit({ vulnerabilities: {} }, beforeExpiry).ok, false);
  assert.equal(
    evaluateAudit(report({ braces: { via: [bracesAdvisory] } }), new Date('2027-01-31T00:00:00.000Z')).ok,
    false
  );
});

function spawnResult(status, body, error = null) {
  return () => ({ status, stdout: JSON.stringify(body), stderr: '', error });
}

test('runner invokes npm audit without a shell and accepts the narrow exception', () => {
  let invocation;
  const spawn = (command, args, options) => {
    invocation = { command, args, options };

    return {
      status: 1,
      stdout: JSON.stringify(report({ braces: { via: [bracesAdvisory] } })),
      stderr: '',
      error: null
    };
  };

  assert.equal(run({ spawn, cliPath: 'npm-cli.js', now: beforeExpiry }), 0);
  assert.equal(invocation.command, process.execPath);
  assert.deepEqual(invocation.args, ['npm-cli.js', 'audit', '--json']);
  assert.equal(invocation.options.shell, false);
});

test('runner fails closed on spawn, audit, parse, and unexpected-advisory errors', () => {
  assert.equal(
    run({
      spawn: spawnResult(null, {}, new Error('spawn failed')),
      cliPath: 'npm-cli.js',
      now: beforeExpiry
    }),
    1
  );
  assert.equal(run({ spawn: spawnResult(2, {}), cliPath: 'npm-cli.js', now: beforeExpiry }), 1);
  assert.equal(
    run({
      spawn: () => ({ status: 0, stdout: 'not json', stderr: '', error: null }),
      cliPath: 'npm-cli.js',
      now: beforeExpiry
    }),
    1
  );
  assert.equal(run({ spawn: spawnResult(1, report({})), cliPath: 'npm-cli.js', now: beforeExpiry }), 1);
  assert.equal(
    run({
      spawn: spawnResult(1, report({ other: { via: [{ url: 'https://github.com/advisories/GHSA-xxxx-yyyy-zzzz' }] } })),
      cliPath: 'npm-cli.js',
      now: beforeExpiry
    }),
    1
  );
});
