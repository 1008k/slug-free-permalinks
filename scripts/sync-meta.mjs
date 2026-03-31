import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

const metadataPath = path.join(rootDir, 'plugin-meta.json');
const pluginMainFile = path.join(rootDir, 'slug-free-permalinks.php');
const wporgReadmeFile = path.join(rootDir, 'readme.txt');
const readmeFile = path.join(rootDir, 'README.md');
const readmeJaFile = path.join(rootDir, 'README-ja.md');
const poFile = path.join(rootDir, 'languages', 'slug-free-permalinks-ja.po');
const l10nPhpFile = path.join(
  rootDir,
  'languages',
  'slug-free-permalinks-ja.l10n.php'
);

function readText(filePath) {
  return fs.readFileSync(filePath, 'utf8');
}

function writeText(filePath, contents) {
  fs.writeFileSync(filePath, contents, 'utf8');
}

function replaceRequired(contents, pattern, replacement, fileLabel) {
  if (!pattern.test(contents)) {
    throw new Error(`Could not update ${fileLabel}. Pattern not found: ${pattern}`);
  }

  return contents.replace(pattern, replacement);
}

export function loadMetadata() {
  return JSON.parse(readText(metadataPath));
}

export function syncMetadata() {
  const metadata = loadMetadata();
  const projectIdVersion = `${metadata.projectName} ${metadata.version}`;

  let pluginContents = readText(pluginMainFile);
  pluginContents = replaceRequired(
    pluginContents,
    /^Plugin Name:\s*.+$/m,
    `Plugin Name: ${metadata.pluginName}`,
    'plugin header name'
  );
  pluginContents = replaceRequired(
    pluginContents,
    /^Plugin URI:\s*.+$/m,
    `Plugin URI: ${metadata.pluginUri}`,
    'plugin header URI'
  );
  pluginContents = replaceRequired(
    pluginContents,
    /^Version:\s*.+$/m,
    `Version: ${metadata.version}`,
    'plugin header version'
  );
  pluginContents = replaceRequired(
    pluginContents,
    /^Requires at least:\s*.+$/m,
    `Requires at least: ${metadata.requiresAtLeast}`,
    'plugin header minimum WordPress version'
  );
  pluginContents = replaceRequired(
    pluginContents,
    /^Requires PHP:\s*.+$/m,
    `Requires PHP: ${metadata.requiresPhp}`,
    'plugin header minimum PHP version'
  );
  pluginContents = replaceRequired(
    pluginContents,
    /^Author:\s*.+$/m,
    `Author: ${metadata.author}`,
    'plugin header author'
  );
  pluginContents = replaceRequired(
    pluginContents,
    /^Author URI:\s*.+$/m,
    `Author URI: ${metadata.authorUri}`,
    'plugin header author URI'
  );
  pluginContents = replaceRequired(
    pluginContents,
    /^License:\s*.+$/m,
    `License: ${metadata.license}`,
    'plugin header license'
  );
  pluginContents = replaceRequired(
    pluginContents,
    /^License URI:\s*.+$/m,
    `License URI: ${metadata.licenseUri}`,
    'plugin header license URI'
  );
  writeText(pluginMainFile, pluginContents);

  let wporgReadmeContents = readText(wporgReadmeFile);
  wporgReadmeContents = replaceRequired(
    wporgReadmeContents,
    /^Contributors:\s*.+$/m,
    `Contributors: ${metadata.wporgContributor}`,
    'readme contributor'
  );
  wporgReadmeContents = replaceRequired(
    wporgReadmeContents,
    /^Requires at least:\s*.+$/m,
    `Requires at least: ${metadata.requiresAtLeast}`,
    'readme minimum WordPress version'
  );
  wporgReadmeContents = replaceRequired(
    wporgReadmeContents,
    /^Tested up to:\s*.+$/m,
    `Tested up to: ${metadata.testedUpTo}`,
    'readme tested up to version'
  );
  wporgReadmeContents = replaceRequired(
    wporgReadmeContents,
    /^Requires PHP:\s*.+$/m,
    `Requires PHP: ${metadata.requiresPhp}`,
    'readme minimum PHP version'
  );
  wporgReadmeContents = replaceRequired(
    wporgReadmeContents,
    /^Stable tag:\s*.+$/m,
    `Stable tag: ${metadata.version}`,
    'readme stable tag'
  );
  wporgReadmeContents = replaceRequired(
    wporgReadmeContents,
    /^Plugin page \(English\):\s*.+$/m,
    `Plugin page (English): ${metadata.pluginPageEnglish}`,
    'English plugin page URL'
  );
  wporgReadmeContents = replaceRequired(
    wporgReadmeContents,
    /^Plugin page \(Japanese\):\s*.+$/m,
    `Plugin page (Japanese): ${metadata.pluginPageJapanese}`,
    'Japanese plugin page URL'
  );
  writeText(wporgReadmeFile, wporgReadmeContents);

  let readmeContents = readText(readmeFile);
  readmeContents = replaceRequired(
    readmeContents,
    /^Plugin page:\s*\[English\]\(.+\) \| \[Japanese\]\(.+\)$/m,
    `Plugin page: [English](${metadata.pluginPageEnglish}) | [Japanese](${metadata.pluginPageJapanese})`,
    'README plugin page links'
  );
  writeText(readmeFile, readmeContents);

  let readmeJaContents = readText(readmeJaFile);
  readmeJaContents = replaceRequired(
    readmeJaContents,
    /^公式ページ:\s*\[English\]\(.+\) \| \[Japanese\]\(.+\)$/m,
    `公式ページ: [English](${metadata.pluginPageEnglish}) | [Japanese](${metadata.pluginPageJapanese})`,
    'Japanese README plugin page links'
  );
  writeText(readmeJaFile, readmeJaContents);

  let poContents = readText(poFile);
  poContents = replaceRequired(
    poContents,
    /^"Project-Id-Version:\s*.+\\n"$/m,
    `"Project-Id-Version: ${projectIdVersion}\\n"`,
    'PO project version'
  );
  poContents = replaceRequired(
    poContents,
    /^"Last-Translator:\s*.+\\n"$/m,
    `"Last-Translator: ${metadata.lastTranslator}\\n"`,
    'PO last translator'
  );
  writeText(poFile, poContents);

  let l10nPhpContents = readText(l10nPhpFile);
  l10nPhpContents = replaceRequired(
    l10nPhpContents,
    /'project-id-version' => '.*?'/,
    `'project-id-version' => '${projectIdVersion}'`,
    'l10n PHP project version'
  );
  writeText(l10nPhpFile, l10nPhpContents);
}

const isDirectRun =
  process.argv[1] && path.resolve(process.argv[1]) === __filename;

if (isDirectRun) {
  syncMetadata();
  console.log('Synchronized plugin metadata');
}
