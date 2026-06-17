import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E — SIRINDU (Paket A–F)
 *
 * Auth: login sekali per role di global.setup.ts → storageState di e2e/.auth/*.json.
 * Spec memilih state-nya sendiri via `test.use({ storageState })`, jadi cukup SATU
 * project browser ("chromium") yang bergantung pada project "setup".
 *
 * Standalone run:
 *   npx playwright test e2e/f-menu-quicklink.spec.ts --project=chromium
 * (project "setup" otomatis dijalankan lebih dulu sebagai dependency.)
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  // Dashboard memicu banyak AJAX paralel; batasi worker agar PHP-FPM tak jenuh.
  workers: process.env.CI ? 1 : 3,
  reporter: 'list',

  use: {
    // Override saat E2E menargetkan server env-testing (reCAPTCHA dibypass):
    //   $env:PLAYWRIGHT_BASE_URL='http://127.0.0.1:8000'
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://sirindu.test',
    headless: true,
    trace: 'on-first-retry',
    video: 'off',
    screenshot: 'only-on-failure',
  },

  projects: [
    // Login semua role sekali, tulis storageState. Wajib lebih dulu.
    { name: 'setup', testMatch: /global\.setup\.ts/ },

    // Satu browser project untuk semua spec. Tiap spec/describe memilih
    // storageState role-nya sendiri (superadmin/surveilans/imunisasi),
    // atau "logged out" untuk auth.spec.ts.
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
      testMatch: /.*\.spec\.ts/,
    },
  ],
});
