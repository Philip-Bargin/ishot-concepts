// Plan §5.1: the prototype source must be byte-identical to the approved commit before any capture.
import { spawnSync } from 'node:child_process';

export const PROTOTYPE_COMMIT = '50e60f4';
export const PROTOTYPE_PATHS = ['src', 'public', 'index.html', 'vite.config.js', 'design-system'];

const gitRun = (args) => spawnSync('git', args, { encoding: 'utf8' });

/** Returns { ok, message }. `run` is injectable for tests. */
export function checkPrototypeIntegrity(run = gitRun) {
  const res = run(['diff', '--quiet', PROTOTYPE_COMMIT, '--', ...PROTOTYPE_PATHS]);
  if (res.status === 0) return { ok: true, message: `Prototype matches ${PROTOTYPE_COMMIT}.` };
  const detail = run(['diff', '--stat', PROTOTYPE_COMMIT, '--', ...PROTOTYPE_PATHS]).stdout ?? '';
  return {
    ok: false,
    message:
      `Prototype integrity check failed: ${PROTOTYPE_PATHS.join(', ')} differ from commit ${PROTOTYPE_COMMIT}.\n` +
      `The visual reference is only valid for the approved prototype. Revert these changes and run again.\n${detail}`,
  };
}

export function assertPrototypeIntegrity(run) {
  const result = checkPrototypeIntegrity(run);
  if (!result.ok) throw new Error(result.message);
  return result;
}
