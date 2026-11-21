// Plan §5.1: the prototype source must be byte-identical to the approved commit before any capture.
import { spawnSync } from 'node:child_process';

export const PROTOTYPE_COMMIT = '50e60f4';
export const PROTOTYPE_PATHS = ['src', 'public', 'index.html', 'vite.config.js', 'design-system'];

const gitRun = (args) => spawnSync('git', args, { encoding: 'utf8' });

/** Returns { ok, message }. `run` is injectable for tests. Fails closed on tracked changes, untracked files or git errors. */
export function checkPrototypeIntegrity(run = gitRun) {
  const diff = run(['diff', '--quiet', PROTOTYPE_COMMIT, '--', ...PROTOTYPE_PATHS]);
  const others = run(['ls-files', '--others', '--exclude-standard', '--', ...PROTOTYPE_PATHS]);
  const untracked = (others.stdout ?? '').trim();
  const problems = [];
  if (diff.status !== 0) {
    const detail =
      diff.status === 1
        ? run(['diff', '--stat', PROTOTYPE_COMMIT, '--', ...PROTOTYPE_PATHS]).stdout ?? ''
        : `git diff exited with ${diff.status}`;
    problems.push(`tracked files differ from commit ${PROTOTYPE_COMMIT}:\n${detail}`);
  }
  if (others.status !== 0) {
    problems.push(`git ls-files exited with ${others.status}, so untracked files could not be ruled out`);
  } else if (untracked) {
    problems.push(`untracked files exist under the prototype paths:\n${untracked}`);
  }
  if (!problems.length) return { ok: true, message: `Prototype matches ${PROTOTYPE_COMMIT}.` };
  return {
    ok: false,
    message:
      `Prototype integrity check failed (${PROTOTYPE_PATHS.join(', ')}).\n${problems.join('\n')}\n` +
      'The visual reference is only valid for the approved prototype. Revert or remove these and run again.',
  };
}

export function assertPrototypeIntegrity(run) {
  const result = checkPrototypeIntegrity(run);
  if (!result.ok) throw new Error(result.message);
  return result;
}
