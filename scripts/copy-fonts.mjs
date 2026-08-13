/**
 * Copies the Tabler icon webfont into public/fonts so the compiled CSS
 * (which points at /fonts/...) resolves at runtime on any host.
 */
import { cpSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const src = resolve(root, 'node_modules/@tabler/icons-webfont/dist/fonts');
const dest = resolve(root, 'public/fonts');

mkdirSync(dest, { recursive: true });
cpSync(src, dest, { recursive: true });

console.log(`copied tabler icon fonts -> ${dest}`);
