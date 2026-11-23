// Run configuration from the environment, shared by playwright.config.mjs and the specs.
//   VISUAL_TARGET   prototype (default) | wp
//   VISUAL_BASE_URL prototype: http://localhost:<VISUAL_PORT|4173>; wp: http://localhost:8888
//   VISUAL_SCOPE    full (default) | frame (wp comparison only) | component (the component gate, components.mjs)
//   VISUAL_STATES   comma list of route names (default: all)
//   VISUAL_COMPONENTS comma list of component names (default: all); implies VISUAL_SCOPE=component
import { resolveScope, selectComponents, selectRoutes } from './scope.mjs';
import { ROUTES } from './routes.mjs';
import { COMPONENTS } from './components.mjs';

const target = process.env.VISUAL_TARGET || 'prototype';
if (!['prototype', 'wp'].includes(target)) throw new Error(`VISUAL_TARGET must be "prototype" or "wp", got "${target}"`);

export const TARGET = target;
export const IS_WP = target === 'wp';
export const BASE_URL = process.env.VISUAL_BASE_URL || (IS_WP ? 'http://localhost:8888' : `http://localhost:${process.env.VISUAL_PORT || 4173}`);
export const SCOPE = resolveScope(process.env.VISUAL_SCOPE, process.env.VISUAL_COMPONENTS);
export const ACTIVE_ROUTES = selectRoutes(ROUTES, process.env.VISUAL_STATES);
export const ACTIVE_COMPONENTS = selectComponents(COMPONENTS, process.env.VISUAL_COMPONENTS);
export const pathFor = (route) => (IS_WP ? route.wpPath : route.prototypePath);
/** A component's URL path and root selector on the current target. */
export const componentTarget = (c) => (IS_WP ? { path: c.wpPath, root: c.wpRoot } : { path: c.prototypePath, root: c.prototypeRoot });
