import { defineConfig, devices } from '@playwright/test';

/**
 * Mobile viewport audit for CashVero Inertia pages.
 *
 * Run: npm run test:mobile
 * Requires: APP_URL (default http://127.0.0.1:8000), E2E_EMAIL, E2E_PASSWORD
 * and a generated catalog at tests/e2e/mobile/catalog.json
 * (php tests/e2e/mobile/page-catalog.php).
 */
const baseURL = process.env.APP_URL || 'http://127.0.0.1:8000';

export default defineConfig({
    testDir: 'tests/e2e/mobile',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    timeout: 60_000,
    expect: { timeout: 15_000 },
    reporter: [
        ['list'],
        ['html', { open: 'never', outputFolder: 'tests/e2e/mobile/playwright-report' }],
        ['json', { outputFile: 'tests/e2e/mobile/report.json' }],
    ],
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'off',
        ...devices['iPhone 12'],
        viewport: { width: 375, height: 812 },
        isMobile: true,
        hasTouch: true,
    },
    outputDir: 'tests/e2e/mobile/artifacts',
    projects: [
        {
            name: 'mobile-setup',
            testMatch: /auth\.setup\.ts/,
        },
        {
            name: 'mobile-audit',
            testMatch: /responsive\.spec\.ts/,
            dependencies: ['mobile-setup'],
            use: {
                storageState: 'tests/e2e/mobile/.auth/user.json',
            },
        },
    ],
});
