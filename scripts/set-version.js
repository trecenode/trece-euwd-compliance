#!/usr/bin/env node
/**
 * Bump the plugin version atomically across the plugin header and the README.
 *
 * The plugin header's `Version:` line is the single source of truth at
 * runtime (TRECE_WDEU_VERSION is derived from it via get_file_data). The
 * README badge is a human-facing duplicate that needs to stay in sync.
 *
 * Usage: npm run version:set 1.4.0
 */

'use strict';

const fs   = require('fs');
const path = require('path');

const version = process.argv[2];

if (!version || !/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/.test(version)) {
  console.error('Usage: npm run version:set <X.Y.Z>');
  process.exit(1);
}

const root = path.join(__dirname, '..');

const targets = [
  {
    file:    path.join(root, 'trece-withdrawal-eu.php'),
    pattern: /^(\s*\*\s*Version:\s*)(\S+)\s*$/m,
  },
  {
    file:    path.join(root, 'README.md'),
    pattern: /^(\*\*Version:\*\*\s*)(\S+)\s*$/m,
  },
];

let failed = false;

for (const t of targets) {
  const rel = path.relative(root, t.file);
  const before = fs.readFileSync(t.file, 'utf8');
  const match = before.match(t.pattern);

  if (!match) {
    console.error(`  miss  ${rel}  — version line not found`);
    failed = true;
    continue;
  }

  if (match[2] === version) {
    console.log(`  skip  ${rel}  — already ${version}`);
    continue;
  }

  const after = before.replace(t.pattern, `$1${version}`);
  fs.writeFileSync(t.file, after);
  console.log(`  bump  ${rel}  ${match[2]} -> ${version}`);
}

if (failed) {
  process.exit(1);
}

console.log(`\nDone. Next: add a ${version} section to CHANGELOG.md, then commit and tag.`);
