// Builds the editor/front-end assets of every block package with `wp-scripts build`.
//
// A block package is a folder with a `blocks/<name>/block.json` source tree and a committed
// `build/blocks/` output (the theme and the ishot-blocks plugin). One `wp-scripts build` runs per
// package, with the source folder and output folder passed explicitly, so a package without
// blocks is never touched. With no blocks anywhere the script says so and exits 0.
//
// Usage: node scripts/build-blocks.mjs            build into <package>/build/blocks
//        node scripts/build-blocks.mjs --check    rebuild into a temp folder and byte-compare with
//                                                 the committed build (CI: fails when stale)
//        --package <dir>  (repeatable) build these packages instead of the default two
import { spawnSync } from "node:child_process";
import { cpSync, existsSync, mkdtempSync, readdirSync, readFileSync, rmSync, statSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const wpScripts = join(root, "node_modules", "@wordpress", "scripts", "bin", "wp-scripts.js");

const argv = process.argv.slice(2);
const check = argv.includes("--check");
const packages = [];
for (let i = 0; i < argv.length; i++) {
  if (argv[i] === "--package") packages.push(resolve(argv[++i] ?? ""));
}
if (packages.length === 0) {
  packages.push(
    join(root, "wordpress", "wp-content", "themes", "ishot-concept"),
    join(root, "wordpress", "wp-content", "plugins", "ishot-blocks"),
  );
}

/** Every block.json directly inside blocks/<name>/ (sorted, so output is deterministic). */
function findBlocks(pkg) {
  const dir = join(pkg, "blocks");
  if (!existsSync(dir)) return [];
  return readdirSync(dir)
    .filter((name) => existsSync(join(dir, name, "block.json")))
    .sort();
}

/** All files under dir as sorted relative POSIX paths (.gitkeep placeholders are not build output). */
function listFiles(dir) {
  if (!existsSync(dir)) return [];
  const out = [];
  const walk = (d) => {
    for (const entry of readdirSync(d).sort()) {
      const full = join(d, entry);
      if (statSync(full).isDirectory()) walk(full);
      else if (entry !== ".gitkeep") out.push(relative(dir, full).split("\\").join("/"));
    }
  };
  walk(dir);
  return out;
}

function build(pkg, outDir) {
  const result = spawnSync(
    process.execPath,
    [
      wpScripts, "build",
      `--webpack-src-dir=${relative(root, join(pkg, "blocks"))}`,
      `--output-path=${relative(root, outDir)}`,
      "--webpack-copy-php",
    ],
    // Always run from the repo root: that is where node_modules (loaders, browserslist config) lives.
    { cwd: root, stdio: ["ignore", "inherit", "inherit"] },
  );
  if (result.status !== 0) {
    console.error(`build-blocks: wp-scripts failed in ${pkg}`);
    process.exit(result.status ?? 1);
  }
}

/** Differences between the committed build and a fresh one, as readable lines. */
function diffTrees(committed, fresh) {
  const a = listFiles(committed);
  const b = listFiles(fresh);
  const problems = [];
  for (const f of a) if (!b.includes(f)) problems.push(`only in committed build: ${f}`);
  for (const f of b) if (!a.includes(f)) problems.push(`missing from committed build: ${f}`);
  for (const f of a.filter((x) => b.includes(x))) {
    if (!readFileSync(join(committed, f)).equals(readFileSync(join(fresh, f)))) problems.push(`differs: ${f}`);
  }
  return problems;
}

let built = 0;
let failed = false;
for (const pkg of packages) {
  const blocks = findBlocks(pkg);
  const committed = join(pkg, "build", "blocks");
  const label = relative(root, pkg) || pkg;

  if (blocks.length === 0) {
    if (check && listFiles(committed).length > 0) {
      console.error(`build-blocks: ${label}: stale build. It has no source blocks but build/blocks is not empty.`);
      failed = true;
    } else {
      console.log(`build-blocks: ${label}: no blocks found, nothing to build.`);
    }
    continue;
  }

  built += blocks.length;
  if (!check) {
    rmSync(committed, { recursive: true, force: true });
    build(pkg, committed);
    console.log(`build-blocks: ${label}: built ${blocks.length} block(s) (${blocks.join(", ")}).`);
    continue;
  }

  const tmp = mkdtempSync(join(tmpdir(), "ishot-blocks-check-"));
  try {
    build(pkg, tmp);
    const problems = diffTrees(committed, tmp);
    if (problems.length > 0) {
      console.error(`build-blocks: ${label}: stale build. Run "npm run build:blocks" and commit the result.`);
      for (const p of problems) console.error(`  ${p}`);
      failed = true;
    } else {
      console.log(`build-blocks: ${label}: build is up to date (${blocks.length} block(s)).`);
    }
  } finally {
    rmSync(tmp, { recursive: true, force: true });
  }
}

if (failed) process.exit(1);
if (built === 0) console.log("build-blocks: no blocks in any package. Nothing built.");
