// Runs the PHP static analysis locally, exactly as CI does: PHPCS (WordPress Coding Standards +
// PHPCompatibility) and PHPStan level 6, from tools/qa.
//
// It uses a throwaway `composer:2` container and keeps tools/qa/vendor in a named Docker volume,
// so nothing is installed on the host and Windows bind-mount quirks (Composer cannot clean up
// some extracted archives on NTFS mounts) never come into play. CI installs tools/qa natively.
//
// Usage: npm run qa            (both)
//        npm run qa -- phpcs   (one of: phpcs, phpcbf, phpstan)
import { spawnSync } from "node:child_process";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const task = process.argv[2] ?? "qa";
if (!["qa", "phpcs", "phpcbf", "phpstan"].includes(task)) {
  console.error(`qa: unknown task ${task}`);
  process.exit(2);
}

const args = [
  "run", "--rm",
  "-v", `${root}:/app`,
  "-v", "ishot-qa-vendor:/app/tools/qa/vendor",
  "-w", "/app/tools/qa",
  "composer:2",
  "sh", "-c", `composer install --no-interaction --no-progress --quiet && composer run --timeout=0 ${task}`,
];
const result = spawnSync("docker", args, { stdio: "inherit", env: { ...process.env, MSYS_NO_PATHCONV: "1" } });
process.exit(result.status ?? 1);
