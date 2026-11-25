// Argument and merge rules for scripts/visual-reference.mjs, kept pure so node --test covers them.

/**
 * Reference mode (no --check) supports the full run (96 states + every component) and the component-only run.
 * Anything that narrows or retargets the run would end in a confusing "Expected 96" failure, so it is refused.
 * `flags` are the parsed CLI values, `env` the process environment; returns { env, error }.
 */
export function referenceChildEnv({ flags, env }) {
  const states = flags.states ?? env.VISUAL_STATES;
  const scope = flags.scope ?? env.VISUAL_SCOPE;
  if (states) return { env: {}, error: 'Reference mode does not take --states/VISUAL_STATES: it regenerates every page state (or, with --scope component, components).' };
  if (scope && !['full', 'component'].includes(scope)) return { env: {}, error: `Reference mode does not take --scope ${scope}: use no scope (full) or --scope component.` };
  const child = { VISUAL_STATES: '', VISUAL_SCOPE: scope === 'component' || flags.components || env.VISUAL_COMPONENTS ? 'component' : '' };
  const components = flags.components ?? env.VISUAL_COMPONENTS;
  if (components) child.VISUAL_COMPONENTS = components;
  return { env: child, error: '' };
}

/** Why new component partials cannot be merged into an existing components.json ('' when they can). */
export function metaMergeProblem(previous, next) {
  if (!previous) return '';
  for (const field of ['browser', 'channel']) {
    if (previous[field] !== next[field]) {
      return `Refusing to merge: components.json was captured with ${field} "${previous[field]}", this run used "${next[field]}". Run a full reference (npm run visual:reference) instead.`;
    }
  }
  return '';
}

/** components.json text: keys sorted at every level the harness controls, so the same input is byte-identical. */
export function serialiseComponents(meta, components) {
  const sortedComponents = Object.fromEntries(Object.entries(components).sort(([a], [b]) => a.localeCompare(b)));
  return `${JSON.stringify({ meta, components: sortedComponents }, null, 1)}\n`;
}
