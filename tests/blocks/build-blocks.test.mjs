// node --test tests/blocks/build-blocks.test.mjs
// Exercises scripts/build-blocks.mjs against throwaway fixture packages in the OS temp folder.
import { test } from "node:test";
import assert from "node:assert/strict";
import { spawn, spawnSync } from "node:child_process";
import { cpSync, mkdtempSync, mkdirSync, readdirSync, writeFileSync, readFileSync, existsSync, rmSync } from "node:fs";
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

test("--watch together with --check is an error", () => {
  const pkg = makePackage(true);
  const rec = mkdtempSync(join(tmpdir(), "ishot-blocks-recorder-"));
  try {
    const { stub, calls } = makeRecorder(rec);
    const r = runStubbed(pkg, stub, "--watch", "--check");
    assert.notEqual(r.status, 0, r.stdout + r.stderr);
    assert.match(r.stderr, /--watch.*--check|--check.*--watch/);
    assert.equal(calls().length, 0, "nothing is built or watched");
  } finally {
    rmSync(pkg, { recursive: true, force: true });
    rmSync(rec, { recursive: true, force: true });
  }
});

/**
 * A stand-in for `wp-scripts start`. Like the real one (`@wordpress/scripts/utils/cli.js` runs
 * `scripts/start.js` with `spawn.sync`), a watcher blocks on a grandchild that does the watching.
 * Every process records its pid: <dir>/pids/c-<pid>.pid (watcher) and g-<pid>.pid (grandchild).
 * The package whose source path contains "fails" has no grandchild and exits 3 after a moment.
 */
function makeWatcherStub(dir) {
  const pids = join(dir, "pids");
  mkdirSync(pids, { recursive: true });
  const grandchild = join(dir, "webpack-watch-stub.mjs");
  writeFileSync(
    grandchild,
    [
      'import { writeFileSync } from "node:fs";',
      'import { join } from "node:path";',
      `writeFileSync(join(${JSON.stringify(pids)}, "g-" + process.pid + ".pid"), "");`,
      "setInterval(() => {}, 1000);",
    ].join(String.fromCharCode(10)),
  );
  const stub = join(dir, "wp-scripts-watch-stub.mjs");
  writeFileSync(
    stub,
    [
      'import { spawnSync } from "node:child_process";',
      'import { writeFileSync } from "node:fs";',
      'import { join } from "node:path";',
      `writeFileSync(join(${JSON.stringify(pids)}, "c-" + process.pid + ".pid"), process.argv.slice(2).join(" "));`,
      'const fails = process.argv.some((a) => a.startsWith("--webpack-src-dir=") && a.includes("fails"));',
      "if (fails) setTimeout(() => process.exit(3), 1500);",
      `else spawnSync(process.execPath, [${JSON.stringify(grandchild)}], { stdio: "inherit" });`,
    ].join(String.fromCharCode(10)),
  );
  return { stub, pids };
}

const recorded = (pids, prefix) =>
  readdirSync(pids)
    .filter((f) => f.startsWith(prefix))
    .map((f) => Number(f.slice(prefix.length).replace(".pid", "")));

async function waitFor(cond, ms) {
  const deadline = Date.now() + ms;
  while (!cond() && Date.now() < deadline) await new Promise((res) => setTimeout(res, 100));
}

function alive(pid) {
  try {
    process.kill(pid, 0);
    return true;
  } catch {
    return false;
  }
}

function makeNamedPackage(name) {
  const base = mkdtempSync(join(tmpdir(), "ishot-blocks-watch-"));
  const src = makePackage(true);
  const pkg = join(base, name);
  cpSync(src, pkg, { recursive: true });
  rmSync(src, { recursive: true, force: true });
  return { base, pkg };
}

/** Kills whatever a failed assertion left running, so a red run does not leak processes. */
function reap(pids) {
  for (const f of existsSync(pids) ? readdirSync(pids) : []) {
    const pid = Number(f.replace(/^[cg]-/, "").replace(".pid", ""));
    try {
      process.kill(pid, "SIGKILL");
    } catch {
      // already gone
    }
  }
}

test("--watch stops the other watchers, and their own child processes, when one of them exits", { timeout: 60000 }, async () => {
  const ok = makeNamedPackage("keeps-running");
  const bad = makeNamedPackage("fails");
  const rec = mkdtempSync(join(tmpdir(), "ishot-blocks-recorder-"));
  const { stub, pids } = makeWatcherStub(rec);
  try {
    const r = spawnSync(process.execPath, [script, "--package", ok.pkg, "--package", bad.pkg, "--watch"], {
      encoding: "utf8",
      env: { ...process.env, ISHOT_WP_SCRIPTS: stub },
      timeout: 30000,
    });
    assert.equal(r.error, undefined, "the script must not hang waiting for the surviving watcher");
    assert.equal(r.status, 3, r.stdout + r.stderr);
    assert.equal(recorded(pids, "c-").length, 2);
    assert.equal(recorded(pids, "g-").length, 1, "the surviving watcher had started its grandchild");
    // Give the OS a moment to reap the killed processes.
    await new Promise((res) => setTimeout(res, 1000));
    for (const pid of [...recorded(pids, "c-"), ...recorded(pids, "g-")]) assert.equal(alive(pid), false, `process ${pid} left running`);
  } finally {
    reap(pids);
    for (const p of [ok.base, bad.base, rec]) rmSync(p, { recursive: true, force: true });
  }
});

// Windows has no catchable SIGTERM/SIGINT from process.kill (it terminates outright), and a console
// Ctrl+C already reaches every process on the console; forwarding matters on POSIX (CI, Docker).
test("--watch forwards SIGINT, SIGTERM and SIGHUP to the watchers' process groups and leaves none behind", { timeout: 90000, skip: process.platform === "win32" && "POSIX signals only" }, async () => {
  for (const [signal, code] of [["SIGINT", 130], ["SIGTERM", 143], ["SIGHUP", 129]]) {
    const a = makeNamedPackage("one");
    const b = makeNamedPackage("two");
    const rec = mkdtempSync(join(tmpdir(), "ishot-blocks-recorder-"));
    const { stub, pids } = makeWatcherStub(rec);
    try {
      const child = spawn(process.execPath, [script, "--package", a.pkg, "--package", b.pkg, "--watch"], {
        env: { ...process.env, ISHOT_WP_SCRIPTS: stub },
        stdio: "ignore",
      });
      await waitFor(() => recorded(pids, "g-").length >= 2, 20000);
      assert.equal(recorded(pids, "c-").length, 2);
      assert.equal(recorded(pids, "g-").length, 2);
      const exited = new Promise((res) => child.on("exit", (c, sig) => res({ code: c, sig })));
      child.kill(signal);
      const result = await Promise.race([exited, new Promise((res) => setTimeout(() => res("timeout"), 15000))]);
      assert.notEqual(result, "timeout", `${signal}: the script did not exit`);
      assert.equal(result.code, code, `${signal}: exit code`);
      await new Promise((res) => setTimeout(res, 1000));
      for (const pid of [...recorded(pids, "c-"), ...recorded(pids, "g-")]) assert.equal(alive(pid), false, `${signal}: process ${pid} left running`);
    } finally {
      reap(pids);
      for (const p of [a.base, b.base, rec]) rmSync(p, { recursive: true, force: true });
    }
  }
});
