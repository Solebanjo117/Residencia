import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';

test('jefe de oficina can access review queue and is blocked from docente routes', async ({ page }) => {
    await loginAs(page, 'oficina@example.com', 'password');

    await page.goto('/oficina/revisiones');
    await expect(page.getByRole('heading', { name: 'Aprobación de Evidencias' })).toBeVisible();

    const forbidden = await page.goto('/docente/evidencias');
    expect(forbidden?.status()).toBe(403);
});
