import { expect, test } from './support/test';

test.describe('Restaurant identity', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/login');
        await page
            .getByLabel('Correo electrónico')
            .fill('playwright-admin@example.test');
        await page.locator('#password').fill('playwright-password');
        await page
            .getByRole('button', { name: 'Iniciar sesión', exact: true })
            .click();
        await expect(page).toHaveURL(/\/admin$/);
    });

    test('a renamed restaurant names the page titles without a reload', async ({
        page,
    }) => {
        await page.goto('/admin/settings/general');
        await expect(page).toHaveTitle('Configuración general - Te Pinta');

        await page
            .getByLabel('Nombre del restaurante', { exact: true })
            .fill('La Esquina');
        await page
            .getByRole('button', {
                name: 'Guardar nombre y textos',
                exact: true,
            })
            .click();
        await expect(page).toHaveTitle('Configuración general - La Esquina');

        await page
            .getByRole('link', { name: 'Pedidos', exact: true })
            .first()
            .click();
        await expect(page).toHaveURL(/\/admin\/orders$/);
        await expect(page).toHaveTitle(/ - La Esquina$/);
    });
});
