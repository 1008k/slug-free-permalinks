import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { getPluginVersion } from './build-dist.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const pluginMainFile = path.join(rootDir, 'slug-free-permalinks.php');
const readmeFile = path.join(rootDir, 'readme.txt');
const semanticVersionPattern = /^\d+\.\d+\.\d+$/;

export function readPluginHeaderVersion(contents) {
  const match = contents.match(
    /^[ \t]*(?:\*[ \t]*)?Version:[ \t]*(\S+)[ \t]*$/im
  );

  if (!match) {
    throw new Error('Could not read Version from slug-free-permalinks.php.');
  }

  return match[1];
}

export function readStableTag(contents) {
  const match = contents.match(/^Stable tag:[ \t]*(\S+)[ \t]*$/im);

  if (!match) {
    throw new Error('Could not read Stable tag from readme.txt.');
  }

  return match[1];
}

export function validateReleaseMetadata(
  expectedVersion,
  { metadataVersion, pluginContents, readmeContents }
) {
  if (!semanticVersionPattern.test(expectedVersion)) {
    throw new Error(
      `Expected a semantic version in x.y.z form, got: ${expectedVersion}`
    );
  }

  const versions = [
    ['Canonical metadata version', metadataVersion],
    ['Plugin header version', readPluginHeaderVersion(pluginContents)],
    ['Stable tag', readStableTag(readmeContents)],
  ];

  for (const [label, actualVersion] of versions) {
    if (actualVersion !== expectedVersion) {
      throw new Error(
        `${label} (${actualVersion}) does not match expected version (${expectedVersion}).`
      );
    }
  }

  return expectedVersion;
}

export function validateRepositoryReleaseMetadata(expectedVersion) {
  return validateReleaseMetadata(expectedVersion, {
    metadataVersion: getPluginVersion(),
    pluginContents: fs.readFileSync(pluginMainFile, 'utf8'),
    readmeContents: fs.readFileSync(readmeFile, 'utf8'),
  });
}

const isDirectRun =
  process.argv[1] && path.resolve(process.argv[1]) === __filename;

if (isDirectRun) {
  try {
    const version = validateRepositoryReleaseMetadata(process.argv[2] ?? '');
    console.log(`Validated release metadata for ${version}.`);
  } catch (error) {
    console.error(error instanceof Error ? error.message : error);
    process.exitCode = 1;
  }
}
