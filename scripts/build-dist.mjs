import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');
const distDir = path.join(rootDir, 'dist', 'slug-free-permalinks');

const filesToCopy = [
  'LICENSE',
  'readme.txt',
  'slug-free-permalinks.php',
  'uninstall.php',
];

const directoriesToCopy = ['languages'];

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

console.log(`Built ${path.relative(rootDir, distDir)}`);
