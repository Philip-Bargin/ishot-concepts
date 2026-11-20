// Generates responsive WebP sizes for every photo in public/images/ into public/images/opt/.
// Usage: npm run images   (re-run after adding or replacing a photo; existing outputs are skipped,
// pass --force to regenerate). Widths must match IMAGE_WIDTHS in src/assets.js.
import { existsSync, mkdirSync, readdirSync, statSync } from "node:fs";
import { basename, dirname, extname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";

const WIDTHS = [480, 960, 1600, 2400];
const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const srcDir = resolve(root, "public/images");
const outDir = resolve(srcDir, "opt");
const force = process.argv.includes("--force");
mkdirSync(outDir, { recursive: true });

const sources = readdirSync(srcDir).filter((f) => /\.(webp|jpe?g|png|avif)$/i.test(f));
let written = 0;

for (const file of sources) {
  const name = basename(file, extname(file));
  for (const width of WIDTHS) {
    const dest = resolve(outDir, `${name}-${width}.webp`);
    if (!force && existsSync(dest)) continue;
    await sharp(resolve(srcDir, file))
      .rotate()
      .resize({ width, withoutEnlargement: true })
      .webp({ quality: 78 })
      .toFile(dest);
    written += 1;
    console.log(`${name}-${width}.webp  ${(statSync(dest).size / 1024).toFixed(0)} KB`);
  }
}
console.log(`optimize-images: ${written} file(s) written, ${sources.length} source photo(s).`);
