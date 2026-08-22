/**
 * After Vite writes compiled assets to dist/, copy static source assets
 * into dist/assets/ for Vercel, and mirror compiled output back to assets/
 * so local PHP (php -S) continues to resolve the same paths.
 */
import { cpSync, existsSync, mkdirSync } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, '..');

const staticCss = [
  'design-system.css',
  'admin.css',
  'style.css',
];

const staticJs = ['main.js'];

const distAssetsCss = path.join(root, 'dist', 'assets', 'css');
const distAssetsJs = path.join(root, 'dist', 'assets', 'js');
const rootAssetsCss = path.join(root, 'assets', 'css');
const rootAssetsJs = path.join(root, 'assets', 'js');

mkdirSync(distAssetsCss, { recursive: true });
mkdirSync(distAssetsJs, { recursive: true });
mkdirSync(rootAssetsCss, { recursive: true });
mkdirSync(rootAssetsJs, { recursive: true });

for (const file of staticCss) {
  const src = path.join(rootAssetsCss, file);
  if (existsSync(src)) {
    cpSync(src, path.join(distAssetsCss, file));
  }
}

for (const file of staticJs) {
  const src = path.join(rootAssetsJs, file);
  if (existsSync(src)) {
    cpSync(src, path.join(distAssetsJs, file));
  }
}

const compiledJs = path.join(distAssetsJs, 'app.min.js');
const compiledCss = path.join(distAssetsCss, 'app.min.css');

if (existsSync(compiledJs)) {
  cpSync(compiledJs, path.join(rootAssetsJs, 'app.min.js'));
}

if (existsSync(compiledCss)) {
  cpSync(compiledCss, path.join(rootAssetsCss, 'app.min.css'));
}

console.log('postbuild: static assets copied to dist/ and mirrored to assets/');
