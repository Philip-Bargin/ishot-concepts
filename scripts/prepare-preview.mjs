// Turns `dist/` into a review-only preview of the React prototype (run after `vite build`).
// This is NOT the production website (production is WordPress). The preview:
//  - tells search engines to stay away three ways: <meta name="robots">, robots.txt and an
//    X-Robots-Tag header on every response (vercel.json);
//  - routes every path to index.html so deep links like /work/... load;
//  - drops the full-size original photos (25 MB) the app never references; only the
//    optimised /images/opt/ sizes are kept.
// Usage: npm run preview:build, then from dist/: npx vercel deploy --target=preview (never --prod)
import { readFileSync, readdirSync, rmSync, writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const dist = resolve(dirname(fileURLToPath(import.meta.url)), "../dist");

const htmlPath = resolve(dist, "index.html");
const html = readFileSync(htmlPath, "utf8");
if (!html.includes('name="robots"')) {
  writeFileSync(htmlPath, html.replace("<head>", '<head>\n    <meta name="robots" content="noindex, nofollow">'));
}

writeFileSync(resolve(dist, "robots.txt"), "User-agent: *\nDisallow: /\n");

writeFileSync(
  resolve(dist, "vercel.json"),
  JSON.stringify(
    {
      // Vercel serves real files first, so this only catches app routes such as /work/...
      rewrites: [{ source: "/(.*)", destination: "/index.html" }],
      headers: [{ source: "/(.*)", headers: [{ key: "X-Robots-Tag", value: "noindex, nofollow" }] }],
    },
    null,
    2
  ) + "\n"
);

let removed = 0;
for (const name of readdirSync(resolve(dist, "images"))) {
  if (name.endsWith(".webp")) {
    rmSync(resolve(dist, "images", name));
    removed++;
  }
}
rmSync(resolve(dist, "images/HOW-TO-ADD-PHOTOS.txt"), { force: true });
console.log(`prepare-preview: noindex meta + robots.txt + vercel.json written; removed ${removed} unused original photos`);
