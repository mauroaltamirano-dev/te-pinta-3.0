import { expect, test } from './support/test';

test('navegación pública expone las páginas principales', async ({ page }) => {
    await page.goto('/');
    await expect(
        page
            .getByRole('link', { name: 'Te Pinta, inicio', exact: true })
            .locator('img'),
    ).toHaveAttribute('src', '/images/brand/logo-completo-blanco.png');
    await expect(
        page.getByRole('heading', {
            name: 'Te pinta una empanada.',
            exact: true,
        }),
    ).toBeVisible();

    await page
        .getByRole('navigation', { name: 'Navegación pública' })
        .getByRole('link', { name: 'Menú', exact: true })
        .click();
    await expect(page).toHaveURL(/\/menu$/);
    await expect(
        page.getByRole('heading', { name: 'Nuestro menú.', exact: true }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Mi carrito', exact: true }).click();
    await expect(page).toHaveURL(/\/cart$/);
    await expect(
        page.getByRole('heading', { name: 'Tu carrito.', exact: true }),
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
            .locator('[style*="mask-image"]'),
    ).toHaveCSS('mask-image', /te-pinta-mark\.svg/);
});
