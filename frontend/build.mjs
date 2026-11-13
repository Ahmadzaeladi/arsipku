/**
 * ArsipKu - bundler.
 *
 * Compiles frontend/main.ts into one classic script at ../assets/js/dist/arsipku.js,
 * intended for optional use alongside the PHP-rendered views. Runtime data always
 * comes from the PHP API; this bundle contains no local fixtures or mock services.
 * No framework, no build pipeline beyond esbuild, matching the PHP-native project rule.
 *
 *   node build.mjs            one build
 *   node build.mjs --watch    rebuild on change
 *   node build.mjs --minify   production bundle
 */

import { build, context } from 'esbuild';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const watch = process.argv.includes('--watch');
const minify = process.argv.includes('--minify');

const options = {
  entryPoints: [join(root, 'main.ts')],
  outfile: resolve(root, '../assets/js/dist/arsipku.js'),
  bundle: true,
  format: 'iife',
  platform: 'browser',
  target: ['es2020'],
  sourcemap: true,
  minify,
  legalComments: 'none',
  logLevel: 'info',
};

if (watch) {
  const server = await context(options);
  await server.watch();
  console.info('[ArsipKu] watch aktif - Ctrl+C untuk berhenti.');
} else {
  await build(options);
}
