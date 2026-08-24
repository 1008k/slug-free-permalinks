import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { buildDist, distDir } from '../../scripts/build-dist.mjs';

const expectedTopLevelEntries = [
  'LICENSE',
  'languages',
  'readme.txt',
  'slug-free-permalinks.php',
  'uninstall.php',
];

test('buildDist creates only the distributable plugin files', () => {
  buildDist();

  const entries = fs.readdirSync(distDir).sort();
  assert.deepEqual(entries, expectedTopLevelEntries);

  for (const requiredFile of [
    'slug-free-permalinks.php',
    'readme.txt',
    'uninstall.php',
  ]) {
    assert.equal(fs.statSync(path.join(distDir, requiredFile)).isFile(), true);
  }

  assert.equal(fs.statSync(path.join(distDir, 'languages')).isDirectory(), true);
});
