import { defineConfig, devices } from '@playwright/test';

import { e2eConfig } from './tests/e2e/config';

const inCi = Boolean(process.env.CI);

export default defineConfig({
  testDir: './tests/e2e',
  outputDir: 'test-results',
  fullyParallel: true,
  forbidOnly: inCi,
  retries: inCi ? 2 : 0,
  workers: inCi ? 1 : undefined,
  reporter: inCi
    ? [
        ['line'],
        ['html', { open: 'never', outputFolder: 'playwright-report' }],
      ]
    : [
        ['list'],
        ['html', { open: 'never', outputFolder: 'playwright-report' }],
      ],
  use: {
    baseURL: e2eConfig.baseUrl,
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
    video: 'off',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
