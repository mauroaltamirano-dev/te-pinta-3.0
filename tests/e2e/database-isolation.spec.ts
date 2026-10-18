import { expect, test } from './support/test';

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
    await page.goto('/admin/settings/general');
});

test('a scenario can persist its own restaurant branding', async ({ page }) => {
    await page.getByLabel('Color de acento', { exact: true }).fill('#1f6f4a');
    await page
        .getByRole('button', { name: 'Guardar color de acento', exact: true })
        .click();
    await expect(page.locator('html')).toHaveCSS('--brand-accent', '#1f6f4a');
    await page.reload();
    await expect(
        page.getByLabel('Color de acento', { exact: true }),
    ).toHaveValue('#1f6f4a');
    // Deliberately leave the change persisted: the next scenario owns its own baseline.
});

test('the next scenario receives the original seed instead of previous writes', async ({
    page,
}) => {
    await expect(
        page.getByLabel('Color de acento', { exact: true }),
    ).toHaveValue('#b84f3f');
    await page.goto('/admin/dashboard');
    await expect(page.getByTestId('dashboard-top-products')).toContainText(
        '12 unidades',
    );
});
