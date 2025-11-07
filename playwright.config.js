// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  // Directory where tests are located
  testDir: './tests/e2e',
  
  // Fail the build on CI if you accidentally left test.only in the source code.
  forbidOnly: !!process.env.CI,
  
  // Retry on CI only
  retries: process.env.CI ? 2 : 0,
  
  // We can run tests in parallel
  workers: process.env.CI ? 1 : undefined,
  
  // Reporter to use. 'html' opens a report on failure.
  reporter: 'html',
  
  use: {
    // Base URL to use in actions like `await page.goto('/')`
    // We'll set this to the WordPress container in our CI config
    baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',

    // Collect trace when retrying the failed test
    trace: 'on-first-retry',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  //
  // The 'webServer' block has been removed.
  //
});