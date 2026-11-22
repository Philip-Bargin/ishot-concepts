// node --test tests/blocks/build-blocks.test.mjs
// Exercises scripts/build-blocks.mjs against throwaway fixture packages in the OS temp folder.
import { test } from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const script = resolve(dirname(fileURLToPath(import.meta.url)), "../../scripts/build-blocks.mjs");

function makePackage(withBlock) {
  const pkg = mkdtempSync(join(tmpdir(), "ishot-blocks-fixture-"));
  mkdirSync(join(pkg, "blocks"), { recursive: true });
  if (withBlock) {
    const dir = join(pkg, "blocks", "sample");
    mkdirSync(dir, { recursive: true });
    writeFileSync(
      join(dir, "block.json"),
      JSON.stringify({
        $schema: "https://schemas.wp.org/trunk/block.json",
        apiVersion: 3,
        name: "ishot/sample",
        title: "Sample",
        category: "widgets",
        editorScript: "file:./index.js",
      }),
    );
    writeFileSync(join(dir, "index.js"), "console.log('sample block v1');\n");
  }
  return pkg;
}

function run(pkg, ...flags) {
  return spawnSync(process.execPath, [script, "--package", pkg, ...flags], { encoding: "utf8" });
}

test("builds a block package into build/blocks", { timeout: 180000 }, () => {
  const pkg = makePackage(true);
  try {
    const r = run(pkg);
    assert.equal(r.status, 0, r.stdout + r.stderr);
    assert.ok(existsSync(join(pkg, "build", "blocks", "sample", "block.json")));
    assert.ok(existsSync(join(pkg, "build", "blocks", "sample", "index.js")));
  } finally {
    rmSync(pkg, { recursive: true, force: true });
  }
});

test("zero blocks exits 0 and says so", () => {
  const pkg = makePackage(false);
  try {
    for (const flags of [[], ["--check"]]) {
      const r = run(pkg, ...flags);
      assert.equal(r.status, 0, r.stdout + r.stderr);
      assert.match(r.stdout, /no blocks/i);
    }
  } finally {
    rmSync(pkg, { recursive: true, force: true });
  }
});

test("--check passes on a fresh build and detects a stale one", { timeout: 300000 }, () => {
  const pkg = makePackage(true);
  try {
    assert.equal(run(pkg).status, 0);
    const fresh = run(pkg, "--check");
    assert.equal(fresh.status, 0, fresh.stdout + fresh.stderr);

    // Change the source without rebuilding: the committed build is now stale.
    writeFileSync(join(pkg, "blocks", "sample", "index.js"), "console.log('sample block v2');\n");
    const stale = run(pkg, "--check");
    assert.notEqual(stale.status, 0, stale.stdout);
    assert.match(stale.stdout + stale.stderr, /stale/i);
    // --check never writes into the package.
    assert.match(readFileSync(join(pkg, "build", "blocks", "sample", "index.js"), "utf8"), /v1/);
  } finally {
    rmSync(pkg, { recursive: true, force: true });
  }
});

test("--check fails when a committed build has no source blocks left", () => {
  const pkg = makePackage(false);
  try {
    mkdirSync(join(pkg, "build", "blocks", "gone"), { recursive: true });
    writeFileSync(join(pkg, "build", "blocks", "gone", "block.json"), "{}");
    const r = run(pkg, "--check");
    assert.notEqual(r.status, 0);
    assert.match(r.stdout + r.stderr, /stale/i);
  } finally {
    rmSync(pkg, { recursive: true, force: true });
  }
});

/** A stand-in for wp-scripts that records its arguments (one JSON line per call) and exits 0. */
function makeRecorder(dir) {
  const log = join(dir, "calls.jsonl");
  const stub = join(dir, "wp-scripts-stub.mjs");
  writeFileSync(
    stub,
    [
      'import { appendFileSync } from "node:fs";',
      `appendFileSync(${JSON.stringify(log)}, JSON.stringify(process.argv.slice(2)) + String.fromCharCode(10));`,
    ].join(String.fromCharCode(10)),
  );
  return { stub, calls: () => (existsSync(log) ? readFileSync(log, "utf8").trim().split("\n").map((l) => JSON.parse(l)) : []) };
}

function runStubbed(pkg, stub, ...flags) {
  return spawnSync(process.execPath, [script, "--package", pkg, ...flags], {
    encoding: "utf8",
    env: { ...process.env, ISHOT_WP_SCRIPTS: stub },
  });
}

test("build, --check and --watch all pass --experimental-modules to wp-scripts", () => {
  const pkg = makePackage(true);
  const rec = mkdtempSync(join(tmpdir(), "ishot-blocks-recorder-"));
  try {
    const { stub, calls } = makeRecorder(rec);
    // --check compares against the committed build; the stub builds nothing, so its verdict is irrelevant here.
    for (const flags of [[], ["--check"], ["--watch"]]) runStubbed(pkg, stub, ...flags);
    const [build, check, watch] = calls();
    assert.equal(calls().length, 3);
    assert.equal(build[0], "build");
    assert.equal(check[0], "build");
    assert.equal(watch[0], "start");
    for (const args of [build, check, watch]) {
      assert.ok(args.includes("--experimental-modules"), JSON.stringify(args));
      assert.ok(args.includes("--webpack-copy-php"), JSON.stringify(args));
    }
    assert.ok(watch.some((a) => a.startsWith("--output-path=") && a.split("\\").join("/").endsWith("build/blocks")), JSON.stringify(watch));
  } finally {
    rmSync(pkg, { recursive: true, force: true });
    rmSync(rec, { recursive: true, force: true });
  }
});

test("--watch skips a package with no blocks", () => {
  const pkg = makePackage(false);
  const rec = mkdtempSync(join(tmpdir(), "ishot-blocks-recorder-"));
  try {
    const { stub, calls } = makeRecorder(rec);
    const r = runStubbed(pkg, stub, "--watch");
    assert.equal(r.status, 0, r.stdout + r.stderr);
    assert.equal(calls().length, 0);
    assert.match(r.stdout, /no blocks/i);
  } finally {
    rmSync(pkg, { recursive: true, force: true });
    rmSync(rec, { recursive: true, force: true });
  }
});

test("a block with a viewScriptModule builds a view.js module and view.asset.php", { timeout: 180000 }, () => {
  const pkg = makePackage(true);
  try {
    const dir = join(pkg, "blocks", "sample");
    const meta = JSON.parse(readFileSync(join(dir, "block.json"), "utf8"));
    meta.viewScriptModule = "file:./view.js";
    writeFileSync(join(dir, "block.json"), JSON.stringify(meta));
    writeFileSync(join(dir, "view.js"), "export const ready = true;\nconsole.log('view module', ready);\n");
    const r = run(pkg);
    assert.equal(r.status, 0, r.stdout + r.stderr);
    assert.ok(existsSync(join(pkg, "build", "blocks", "sample", "view.js")));
    assert.ok(existsSync(join(pkg, "build", "blocks", "sample", "view.asset.php")));
    assert.equal(run(pkg, "--check").status, 0);
  } finally {
    rmSync(pkg, { recursive: true, force: true });
  }
});
