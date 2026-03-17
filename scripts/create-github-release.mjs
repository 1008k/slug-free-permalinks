import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import {
  buildDist,
  buildReleaseZip,
  distRootDir,
  getPluginVersion,
} from './build-dist.mjs';

const ghCandidates = [
  process.env.GH_PATH,
  'gh',
  'C:\\Program Files\\GitHub CLI\\gh.exe',
].filter(Boolean);

function resolveGhPath() {
  for (const candidate of ghCandidates) {
    const result = spawnSync(candidate, ['--version'], { stdio: 'ignore' });
    if (!result.error && result.status === 0) {
      return candidate;
    }
  }

  throw new Error(
    'GitHub CLI was not found. Install gh or set GH_PATH to the executable path.'
  );
}

function runChecked(filePath, args) {
  const result = spawnSync(filePath, args, { stdio: 'inherit' });

  if (result.error) {
    throw result.error;
  }

  if (result.status !== 0) {
    throw new Error(
      `Command failed with exit code ${result.status}: ${filePath} ${args.join(' ')}`
    );
  }
}

function runCapture(filePath, args) {
  const result = spawnSync(filePath, args, {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'inherit'],
  });

  if (result.error) {
    throw result.error;
  }

  if (result.status !== 0) {
    throw new Error(
      `Command failed with exit code ${result.status}: ${filePath} ${args.join(' ')}`
    );
  }

  return result.stdout.trim();
}

const version = getPluginVersion();
const tagName = version;
const zipFilePath = path.join(distRootDir, `slug-free-permalinks-${version}.zip`);
const ghPath = resolveGhPath();
const targetCommitish = runCapture('git', ['rev-parse', 'HEAD']);

runChecked(ghPath, ['auth', 'status']);

buildDist();
console.log(`Built dist/slug-free-permalinks`);

buildReleaseZip();
if (!fs.existsSync(zipFilePath)) {
  throw new Error(`Release ZIP was not created: ${zipFilePath}`);
}
console.log(`Built ${path.relative(process.cwd(), zipFilePath)}`);

runChecked(ghPath, [
  'release',
  'create',
  tagName,
  zipFilePath,
  '--title',
  tagName,
  '--generate-notes',
  '--target',
  targetCommitish,
]);
