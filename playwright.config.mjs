import { defineConfig } from '@playwright/test';

// Browser: Playwright's bundled Chromium (pinned through package-lock.json).
// VISUAL_CHANNEL=chrome switches to the system Chrome if the bundled download is unavailable.
const channel = process.env.VISUAL_CHANNEL || undefined;
const port = Number(process.env.VISUAL_PORT || 4173);

export default defineConfig({
  testDir: 'tests/visual',
  testMatch: '**/*.spec.mjs',
  outputDir: 'tests/visual/.output/results',
  timeout: 120_000,
  workers: 4,
  retries: 0,
  reporter: [['list'], ['html', { outputFolder: 'tests/visual/.output/report', open: 'never' }]],
  use: { baseURL: `http://localhost:${port}`, channel },
  // The prototype build (npm run build) is served by vite preview.
  webServer: {
    command: `npx vite preview --port ${port} --strictPort`,
    url: `http://localhost:${port}`,
    reuseExistingServer: false,
    timeout: 60_000,
  },
});
