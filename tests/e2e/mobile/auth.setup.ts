import { test as setup, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const authFile = path.join(__dirname, '.auth/user.json');

/**
 * Logs in once as the E2E user and saves cookies/localStorage for the
 * audit project. Credentials come from env (never hardcoded).
 */
setup('authenticate', async ({ page }) => {
    const email = process.env.E2E_EMAIL;
    const password = process.env.E2E_PASSWORD;

    if (!email || !password) {
        throw new Error(
            'Set E2E_EMAIL and E2E_PASSWORD to a super-admin account before running the mobile audit.'
        );
    }

    fs.mkdirSync(path.dirname(authFile), { recursive: true });

    await page.goto('/en/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('button.zav-btn-submit').click();

    // After login we land on company picker or a company home.
    await page.waitForURL(/\/(en|ar)(\/|$)/, { timeout: 30_000 });
    await expect(page.locator('body')).toBeVisible();

    await page.context().storageState({ path: authFile });
});
