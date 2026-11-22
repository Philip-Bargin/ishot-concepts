// Runs one Playwright spec for the prototype or the WordPress dev site.
//   node scripts/run-visual-spec.mjs a11y [--target wp] [--states 404,about] [--scope frame]
// The prototype target builds first (vite preview serves the build); the wp target needs the dev site running.
import { spawnSync } from 'node:child_process';
import path from 'node:path';

const args = process.argv.slice(2);
const spec = args.shift();
const flag = (name) => (args.includes(`--${name}`) ? args[args.indexOf(`--${name}`) + 1] : undefined);
const env = { ...process.env };
for (const [name, key] of [['target', 'VISUAL_TARGET'], ['states', 'VISUAL_STATES'], ['scope', 'VISUAL_SCOPE'], ['base-url', 'VISUAL_BASE_URL']]) {
  if (flag(name)) env[key] = flag(name);
}
const root = path.resolve(import.meta.dirname, '..');
const run = (command, e = env) => spawnSync(command, { cwd: root, stdio: 'inherit', shell: true, env: e }).status ?? 1;
if (env.VISUAL_TARGET !== 'wp' && run('npm run build') !== 0) process.exit(1);
process.exit(run(`npx playwright test tests/visual/${spec}.spec.mjs`));
