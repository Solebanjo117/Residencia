import { expect, test } from '@playwright/test';
import { loginAs } from './helpers/auth';

test('jefe de departamento can access admin windows and office review routes', async ({
    page,
}) => {
    await loginAs(page, 'depto@example.com', 'password');

    await page.goto('/admin/windows');
    await expect(
        page.getByRole('heading', { name: 'Ventanas de Entrega' }),
    ).toBeVisible();

    await page.goto('/oficina/revisiones');
    await expect(
        page.getByRole('heading', { name: 'Aprobación de Evidencias' }),
    ).toBeVisible();

    await page.goto('/asesorias');
    await expect(
        page.getByRole('heading', { name: 'Control de Seguimiento Docente' }),
    ).toBeVisible();
});
