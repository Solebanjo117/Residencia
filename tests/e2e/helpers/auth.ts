import { expect, type Page } from '@playwright/test';

export async function loginAs(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/login');

    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);

    await Promise.all([
        page.waitForURL(/\/(dashboard|docente\/dashboard)/),
        page.locator('[data-test="login-button"]').click(),
    ]);

    await expect(page).not.toHaveURL(/\/login/);
}
