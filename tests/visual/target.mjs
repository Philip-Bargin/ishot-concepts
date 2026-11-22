// Run configuration from the environment, shared by playwright.config.mjs and the specs.
//   VISUAL_TARGET   prototype (default) | wp
//   VISUAL_BASE_URL prototype: http://localhost:<VISUAL_PORT|4173>; wp: http://localhost:8888
//   VISUAL_SCOPE    full (default) | frame   (wp comparison only)
//   VISUAL_STATES   comma list of route names (default: all)
import { normalizeScope, selectRoutes } from './scope.mjs';
import { ROUTES } from './routes.mjs';

const target = process.env.VISUAL_TARGET || 'prototype';
if (!['prototype', 'wp'].includes(target)) throw new Error(`VISUAL_TARGET must be "prototype" or "wp", got "${target}"`);

export const TARGET = target;
export const IS_WP = target === 'wp';
export const BASE_URL = process.env.VISUAL_BASE_URL || (IS_WP ? 'http://localhost:8888' : `http://localhost:${process.env.VISUAL_PORT || 4173}`);
export const SCOPE = normalizeScope(process.env.VISUAL_SCOPE);
export const ACTIVE_ROUTES = selectRoutes(ROUTES, process.env.VISUAL_STATES);
export const pathFor = (route) => (IS_WP ? route.wpPath : route.prototypePath);
