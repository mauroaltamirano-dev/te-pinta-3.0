import { expect, test } from '@playwright/test';

test('navegación pública expone las páginas principales', async ({ page }) => {
    await page.goto('/');
    await expect(
        page
            .getByRole('link', { name: 'Te Pinta', exact: true })
            .locator('img'),
    ).toHaveAttribute('src', '/icons/te-pinta-logo.svg');
    await expect(
        page.getByRole('heading', { name: 'Tu próximo pedido empieza acá.' }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Menú' }).click();
    await expect(
        page.getByRole('heading', { name: 'Menú', exact: true }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Carrito', exact: true }).click();
    await expect(
        page.getByRole('heading', { name: 'Carrito', exact: true }),
    ).toBeVisible();
});

test('admin redirige visitantes al inicio de sesión', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/login$/);
    await expect(
        page.getByRole('heading', { name: 'Administración de Te Pinta' }),
    ).toBeVisible();
    await expect(
        page
            .getByRole('link', { name: 'Ir al inicio de Te Pinta' })
            .locator('img'),
    ).toHaveAttribute('src', '/icons/te-pinta-logo.svg');
});
