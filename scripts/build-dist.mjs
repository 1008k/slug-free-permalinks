import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const pluginSlug = 'slug-free-permalinks';
const distRootDir = path.join(rootDir, 'dist');
const distDir = path.join(distRootDir, pluginSlug);
const pluginMainFile = path.join(rootDir, `${pluginSlug}.php`);

const filesToCopy = [
  'LICENSE',
  'readme.txt',
  'slug-free-permalinks.php',
  'uninstall.php',
];

const directoriesToCopy = ['languages'];

export function getPluginVersion() {
  const pluginContents = fs.readFileSync(pluginMainFile, 'utf8');
  const versionMatch = pluginContents.match(/^Version:\s*(.+)$/m);

  if (!versionMatch) {
    throw new Error(`Could not find Version header in ${pluginMainFile}`);
  }

  return versionMatch[1].trim();
}

export function buildDist() {
  fs.rmSync(distDir, { recursive: true, force: true });
  fs.mkdirSync(distDir, { recursive: true });

  for (const relativePath of filesToCopy) {
    fs.copyFileSync(
      path.join(rootDir, relativePath),
      path.join(distDir, relativePath)
    );
  }

  for (const relativePath of directoriesToCopy) {
    fs.cpSync(
      path.join(rootDir, relativePath),
      path.join(distDir, relativePath),
      { recursive: true }
    );
  }

  return distDir;
}

export function buildReleaseZip() {
  const version = getPluginVersion();
  const zipFileName = `${pluginSlug}-${version}.zip`;
  const zipFilePath = path.join(distRootDir, zipFileName);

  fs.rmSync(zipFilePath, { force: true });

  const result = spawnSync(
    'tar',
    ['-a', '-cf', zipFileName, pluginSlug],
    {
      cwd: distRootDir,
      stdio: 'inherit',
    }
  );

  if (result.error) {
    throw result.error;
  }

  if (result.status !== 0) {
    throw new Error(`tar failed with exit code ${result.status}`);
  }

  return zipFilePath;
}

const isDirectRun =
  process.argv[1] && path.resolve(process.argv[1]) === __filename;

if (isDirectRun) {
  const shouldZip = process.argv.includes('--zip');

  buildDist();
  console.log(`Built ${path.relative(rootDir, distDir)}`);

  if (shouldZip) {
    const zipFilePath = buildReleaseZip();
    console.log(`Built ${path.relative(rootDir, zipFilePath)}`);
  }
}

export { pluginSlug, distDir, distRootDir };
