#!/usr/bin/env node
/**
 * Cut a release locally: bump the version, rebuild assets, commit, tag,
 * and package a drop-in zip at dist/trece-withdrawal-eu-X.Y.Z.zip.
 *
 * Does NOT push or create a GitHub release — those are explicit follow-ups:
 *
 *   git push --follow-tags
 *   gh release create X.Y.Z dist/trece-withdrawal-eu-X.Y.Z.zip
 *
 * The zip includes both minified and unminified assets so SCRIPT_DEBUG
 * keeps working on the installed copy.
 *
 * Usage: npm run release <X.Y.Z>
 */

'use strict';

const fs           = require('fs');
const path         = require('path');
const { execSync } = require('child_process');

const version = process.argv[2];

if (!version || !/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/.test(version)) {
  console.error('Usage: npm run release <X.Y.Z>');
  process.exit(1);
}

const root = path.join(__dirname, '..');
process.chdir(root);

function sh(cmd) {
  console.log(`$ ${cmd}`);
  execSync(cmd, { stdio: 'inherit' });
}

function shCapture(cmd) {
  return execSync(cmd, { encoding: 'utf8' }).trim();
}

function bail(msg) {
  console.error(`error: ${msg}`);
  process.exit(1);
}

// --- Preflight checks ------------------------------------------------------
//
// Every check below runs BEFORE any disk-mutating step (version bump, build,
// commit, tag). A failure here leaves the working tree untouched.

if (!fs.existsSync(path.join(root, 'node_modules', '.bin', 'esbuild'))) {
  bail('esbuild not installed — run `npm install` first');
}

if (shCapture('git status --porcelain')) {
  bail('working tree is not clean — commit or stash changes first');
}

if (shCapture(`git tag --list ${version}`)) {
  bail(`tag ${version} already exists`);
}

const changelog  = fs.readFileSync('CHANGELOG.md', 'utf8');
const escVersion = version.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
if (!new RegExp(`^## ${escVersion}\\b`, 'm').test(changelog)) {
  bail(`CHANGELOG.md has no '## ${version}' section — add release notes first`);
}

// --- Bump version + rebuild assets -----------------------------------------

sh(`node scripts/set-version.js ${version}`);
sh('npm run build');

// --- Commit + tag ----------------------------------------------------------

if (shCapture('git status --porcelain')) {
  sh('git add trece-withdrawal-eu.php README.md assets');
  sh(`git commit -m '${version}'`);
} else {
  console.log('(no file changes after bump/build; tagging current HEAD as-is)');
}

sh(`git tag -a ${version} -m 'Release ${version}'`);

// --- Stage tracked files from the tag, prune dev meta, zip -----------------

const distDir   = path.join(root, 'dist');
const stageRoot = path.join(distDir, 'trece-withdrawal-eu');

fs.rmSync(distDir, { recursive: true, force: true });
fs.mkdirSync(stageRoot, { recursive: true });

// git archive ships only files tracked at the tagged commit — no node_modules,
// no doc/, no working-tree drift.
sh(`git archive --format=tar ${version} | tar -x -C '${stageRoot}'`);

// Strip dev-only meta from the staged tree.
const distExclude = [
  '.distignore',
  '.gitignore',
  'scripts',
  'package.json',
  'doc',
];

for (const p of distExclude) {
  fs.rmSync(path.join(stageRoot, p), { recursive: true, force: true });
}

const zipName = `trece-withdrawal-eu-${version}.zip`;
sh(`cd '${distDir}' && zip -rq '${zipName}' trece-withdrawal-eu`);

// --- Summary ---------------------------------------------------------------

const sha = shCapture('git rev-parse --short HEAD');

console.log(`
Released ${version} locally.
  commit  ${sha}  ('${version}')
  tag     ${version}
  zip     dist/${zipName}

To publish:
  git push --follow-tags
  gh release create ${version} dist/${zipName} --title '${version}' --notes-file <(awk '/^## ${escVersion}/{flag=1;next}/^## /{flag=0}flag' CHANGELOG.md)
`);
