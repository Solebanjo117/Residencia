import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';

test('jefe de departamento can access admin windows and is blocked from oficina routes', async ({ page }) => {
    await loginAs(page, 'depto@example.com', 'password');

    await page.goto('/admin/windows');
    await expect(page.getByRole('heading', { name: 'Ventanas de Entrega' })).toBeVisible();

    const forbidden = await page.goto('/oficina/revisiones');
    expect(forbidden?.status()).toBe(403);
});
