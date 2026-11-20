// Design-system quality gate: no raw colours or durations outside the design tokens.
// Scans src/ (except the generated src/styles/ds/) and the booking plugin's admin CSS
// (except its generated tokens.css) for:
//   - hex colours (#abc, #aabbcc, #aabbccdd) in .css/.js/.jsx
//   - millisecond durations (e.g. 300ms) in .css
// Custom-property definitions (lines starting with `--name:`) are allowed: that is where app tokens live.
// Usage: npm run lint:tokens   (exits 1 on any violation)
import { readdirSync, readFileSync, statSync } from "node:fs";
import { dirname, join, relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const srcDir = resolve(root, "src");
const pluginCss = resolve(root, "wordpress/wp-content/plugins/ishot-booking/assets/css");
const skips = [resolve(srcDir, "styles/ds"), resolve(pluginCss, "tokens.css")];

const HEX = /(?<![\w&/-])#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{1}|[0-9a-fA-F]{3}|[0-9a-fA-F]{5})?\b/g;
const MS = /\b\d+(?:\.\d+)?ms\b/g;
const TOKEN_DEF = /^\s*--[\w-]+\s*:/;

function walk(dir) {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name);
    if (skips.some((skip) => path.startsWith(skip))) return [];
    return statSync(path).isDirectory() ? walk(path) : [path];
  });
}

const problems = [];
for (const file of [...walk(srcDir), ...walk(pluginCss)].filter((f) => /\.(css|jsx?|mjs)$/.test(f))) {
  const isCss = file.endsWith(".css");
  readFileSync(file, "utf8")
    .split(/\r?\n/)
    .forEach((line, i) => {
      if (TOKEN_DEF.test(line)) return;
      const hits = [...(line.match(HEX) ?? []), ...(isCss ? line.match(MS) ?? [] : [])];
      for (const hit of hits) problems.push(`${relative(root, file)}:${i + 1}  ${hit}`);
    });
}

if (problems.length) {
  console.error(`lint:tokens: ${problems.length} raw value(s) found; use design tokens instead:\n` + problems.join("\n"));
  process.exit(1);
}
console.log("lint:tokens: OK, no raw colours or durations outside tokens.");
