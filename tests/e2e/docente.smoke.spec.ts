import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';

test('docente can access evidencias and is blocked from oficina routes', async ({ page }) => {
    await loginAs(page, 'docente1@example.com', 'password');

    await page.goto('/docente/evidencias');
    await expect(page.getByRole('heading', { name: 'Mis Entregas y Evidencias' })).toBeVisible();

    const forbidden = await page.goto('/oficina/revisiones');
    expect(forbidden?.status()).toBe(403);
});
