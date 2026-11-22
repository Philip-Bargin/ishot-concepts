// Static check (E-5): every wordpress/dev/*.php must call ishot_dev_guard_or_exit() before any other statement,
// so a new dev script without the guard fails CI.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'wordpress', 'dev');
const scripts = readdirSync(dir).filter((f) => f.endsWith('.php'));

/** Executable statements of a PHP file: comments, the open tag and use/namespace/declare lines are skipped. */
function statements(source) {
  const code = source
    .replace(/^<\?php/, '')
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/(^|\n)[ \t]*(\/\/|#)[^\n]*/g, '$1');
  return code
    .split(';')
    .map((s) => s.trim())
    .filter((s) => s !== '' && !/^(use|namespace|declare)\b/.test(s));
}

test('there are dev scripts to check', () => {
  assert.ok(scripts.length >= 7);
});

for (const file of scripts) {
  test(`${file} guards before any other statement`, () => {
    const [first, second] = statements(readFileSync(join(dir, file), 'utf8'));
    assert.match(first ?? '', /^require_once\s+__DIR__\s*\.\s*'\/lib\/guard\.php'$/, 'first statement must load lib/guard.php');
    assert.equal(second, 'ishot_dev_guard_or_exit()', 'second statement must be the guard call');
  });
}
