// Builds the editor/front-end assets of every block package with `wp-scripts build`.
//
// A block package is a folder with a `blocks/<name>/block.json` source tree and a committed
// `build/blocks/` output (the theme and the ishot-blocks plugin). One `wp-scripts build` runs per
// package, with the source folder and output folder passed explicitly, so a package without
// blocks is never touched. With no blocks anywhere the script says so and exits 0.
//
// Every call passes --experimental-modules, so `viewScriptModule` entries (Interactivity API) are built
// as ES modules with a .asset.php. ISHOT_WP_SCRIPTS overrides the wp-scripts binary (used by the tests).
//
// Usage: node scripts/build-blocks.mjs            build into <package>/build/blocks
//        node scripts/build-blocks.mjs --check    rebuild into a temp folder and byte-compare with
//                                                 the committed build (CI: fails when stale)
//        node scripts/build-blocks.mjs --watch    development: `wp-scripts start` for every package with blocks,
//                                                 rebuilding into <package>/build/blocks on change; if one
//                                                 watcher exits the others are stopped, and signals are
//                                                 forwarded (--watch with --check is an error)
//        --package <dir>  (repeatable) build these packages instead of the default two
import { spawn, spawnSync } from "node:child_process";
import { cpSync, existsSync, mkdtempSync, readdirSync, readFileSync, rmSync, statSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const wpScripts = process.env.ISHOT_WP_SCRIPTS || join(root, "node_modules", "@wordpress", "scripts", "bin", "wp-scripts.js");

const argv = process.argv.slice(2);
const check = argv.includes("--check");
const watch = argv.includes("--watch");
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

/** Arguments for `wp-scripts <command>`; always from the repo root, with ES-module view scripts enabled. */
function wpArgs(command, pkg, outDir) {
  return [
    wpScripts, command,
    `--webpack-src-dir=${relative(root, join(pkg, "blocks"))}`,
    `--output-path=${relative(root, outDir)}`,
    "--webpack-copy-php",
    "--experimental-modules",
  ];
}

function build(pkg, outDir) {
  const result = spawnSync(
    process.execPath,
    wpArgs("build", pkg, outDir),
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

if (watch && check) {
  console.error("build-blocks: --watch and --check cannot be combined (--check is a one-off CI comparison).");
  process.exit(2);
}

if (watch) {
  // One long-running `wp-scripts start` per package that has blocks. They live and die together:
  // when one exits, the others are stopped and its exit code is returned; SIGINT/SIGTERM/SIGHUP sent
  // to this script are forwarded to every watcher, so none is left running on its own.
  // wp-scripts runs webpack in a grandchild (`spawn.sync` in @wordpress/scripts/utils/cli.js), so a
  // watcher is stopped as a whole tree: on POSIX each watcher leads its own process group (detached)
  // and the group is signalled; on Windows `taskkill /T /F` ends the process and its descendants.
  const watchers = packages.filter((pkg) => {
    const has = findBlocks(pkg).length > 0;
    if (!has) console.log(`build-blocks: ${relative(root, pkg) || pkg}: no blocks found, nothing to watch.`);
    return has;
  });
  const windows = process.platform === "win32";
  const children = new Set();
  let exitCode = 0;
  let stopping = false;
  const killTree = (child, signal) => {
    if (windows) {
      spawnSync("taskkill", ["/T", "/F", "/PID", String(child.pid)], { stdio: "ignore" });
      return;
    }
    try {
      process.kill(-child.pid, signal);
    } catch {
      // The group is already gone.
    }
  };
  const stopAll = (signal = "SIGTERM") => {
    stopping = true;
    for (const child of children) killTree(child, signal);
  };
  // Conventional 128 + signal number exit codes.
  const SIGNAL_EXIT = { SIGHUP: 129, SIGINT: 130, SIGTERM: 143 };
  for (const signal of Object.keys(SIGNAL_EXIT)) {
    process.on(signal, () => {
      if (!exitCode) exitCode = SIGNAL_EXIT[signal];
      stopAll(signal);
    });
  }
  await Promise.all(
    watchers.map(
      (pkg) =>
        new Promise((done) => {
          const child = spawn(process.execPath, wpArgs("start", pkg, join(pkg, "build", "blocks")), {
            cwd: root,
            stdio: ["ignore", "inherit", "inherit"],
            detached: !windows,
          });
          children.add(child);
          child.on("exit", (code, signal) => {
            children.delete(child);
            // Its own webpack grandchild may outlive it: end the rest of its group too (POSIX).
            if (!windows) killTree(child, "SIGTERM");
            if (!stopping) {
              console.error(`build-blocks: the watcher for ${relative(root, pkg) || pkg} stopped (${signal ?? `exit ${code}`}); stopping the others.`);
              exitCode = code || 1;
              stopAll();
            }
            done();
          });
        }),
    ),
  );
  process.exit(exitCode);
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
