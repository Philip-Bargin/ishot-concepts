import { defineConfig } from '@playwright/test';

// Behaviour tests against the running wp-env dev site (npm run wp:start): no web server is started.
// VISUAL_CHANNEL=chrome switches to the system Chrome if the bundled Chromium is unavailable.
export default defineConfig({
  testDir: 'tests/e2e',
  testMatch: '**/*.spec.mjs',
  outputDir: 'tests/e2e/.output',
  timeout: 60_000,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: { baseURL: process.env.E2E_BASE_URL || 'http://localhost:8888', channel: process.env.VISUAL_CHANNEL || undefined },
});
