'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const ALLOWED_ADVISORY = 'GHSA-vfj7-8cjw-p6xm';
const EXCEPTION_EXPIRES_AT = '2027-01-31T00:00:00.000Z';

function advisoryId(finding) {
  const searchable = [finding?.url, finding?.title].filter(Boolean).join(' ');
  const match = searchable.match(/GHSA-[a-z0-9-]+/i);

  return match ? match[0].toUpperCase() : null;
}

function findingIsAllowed(name, vulnerabilities, ancestry = new Set()) {
  const vulnerability = vulnerabilities[name];

  if (!vulnerability || !Array.isArray(vulnerability.via) || vulnerability.via.length === 0) {
    return false;
  }

  if (ancestry.has(name)) {
    return false;
  }

  const nextAncestry = new Set(ancestry).add(name);

  return vulnerability.via.every(via => {
    if (typeof via === 'string') {
      return findingIsAllowed(via, vulnerabilities, nextAncestry);
    }

    return advisoryId(via) === ALLOWED_ADVISORY.toUpperCase();
  });
}

function evaluateAudit(report, now = new Date()) {
  if (!report || report.auditReportVersion !== 2 || typeof report.vulnerabilities !== 'object') {
    return { ok: false, reason: 'npm audit returned an unsupported or malformed report.', unexpected: [] };
  }

  const names = Object.keys(report.vulnerabilities);

  if (names.length === 0) {
    return { ok: true, allowed: [], unexpected: [] };
  }

  if (now >= new Date(EXCEPTION_EXPIRES_AT)) {
    return {
      ok: false,
      reason: `The temporary ${ALLOWED_ADVISORY} exception expired on 2027-01-31.`,
      unexpected: names
    };
  }

  const allowed = names.filter(name => findingIsAllowed(name, report.vulnerabilities));
  const unexpected = names.filter(name => !allowed.includes(name));

  return {
    ok: unexpected.length === 0,
    allowed,
    unexpected,
    reason: unexpected.length > 0 ? 'npm audit found vulnerabilities outside the narrow exception.' : null
  };
}

function npmCliPath() {
  if (process.env.npm_execpath && fs.existsSync(process.env.npm_execpath)) {
    return process.env.npm_execpath;
  }

  return path.join(path.dirname(process.execPath), 'node_modules', 'npm', 'bin', 'npm-cli.js');
}

function run(options = {}) {
  const spawn = options.spawn || spawnSync;
  const cliPath = options.cliPath || npmCliPath();
  const result = spawn(process.execPath, [cliPath, 'audit', '--json'], {
    encoding: 'utf8',
    shell: false,
    maxBuffer: 10 * 1024 * 1024
  });

  if (result.error) {
    console.error(`Unable to run npm audit: ${result.error.message}`);
    return 1;
  }

  if (![0, 1].includes(result.status)) {
    console.error(`npm audit failed with exit code ${result.status ?? 'unknown'}.`);
    return 1;
  }

  let report;

  try {
    report = JSON.parse(result.stdout);
  } catch {
    console.error('npm audit did not return valid JSON.');
    return 1;
  }

  if (result.status === 1 && Object.keys(report.vulnerabilities || {}).length === 0) {
    console.error('npm audit failed without reporting a vulnerability.');
    return 1;
  }

  const evaluation = evaluateAudit(report, options.now);

  if (!evaluation.ok) {
    console.error(evaluation.reason);
    if (evaluation.unexpected.length > 0) {
      console.error(`Unexpected findings: ${evaluation.unexpected.join(', ')}`);
    }
    return 1;
  }

  if (evaluation.allowed.length > 0) {
    console.warn(
      `Temporarily allowing only ${ALLOWED_ADVISORY} and its derived parent findings: ${evaluation.allowed.join(', ')}.`
    );
    console.warn('Remove this exception as soon as the dependency tree can use a patched braces release.');
  } else {
    console.log('npm audit found no vulnerabilities.');
  }

  return 0;
}

if (require.main === module) {
  process.exitCode = run();
}

module.exports = {
  ALLOWED_ADVISORY,
  EXCEPTION_EXPIRES_AT,
  evaluateAudit,
  findingIsAllowed,
  npmCliPath,
  run
};
