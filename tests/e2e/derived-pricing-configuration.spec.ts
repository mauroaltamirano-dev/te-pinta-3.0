import { expect, test } from './support/test';

test('configures homogeneous surcharge with authoritative preview and ignores stale responses', async ({
    page,
}) => {
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
    await page.goto('/admin/settings/products');
    await page.getByRole('button', { name: 'Agregar producto' }).click();
    const dialog = page.getByRole('dialog');
    await dialog
        .getByRole('button', { name: 'Configurar media mediante recargo' })
        .click();
    await dialog.getByLabel('Docena', { exact: true }).fill('22000');
    await dialog.getByLabel('Recargo de media homogénea').fill('2000');
    await expect(dialog.getByRole('status')).toHaveText(
        'Media homogénea resultante: $ 13.000',
    );
    await page.route(
        '**/admin/settings/products/half-dozen-preview',
        async (route) => {
            const body = route.request().postDataJSON();

            if (body.homogeneous_half_dozen_surcharge === '2100') {
                await new Promise((resolve) => setTimeout(resolve, 700));
                await route
                    .fulfill({ json: { half_dozen_price: 13100 } })
                    .catch(() => undefined);
            } else {
                await route.continue();
            }
        },
    );
    await dialog.getByLabel('Recargo de media homogénea').fill('2100');
    await page.waitForRequest(
        (request) =>
            request.url().endsWith('/half-dozen-preview') &&
            request.postDataJSON()?.homogeneous_half_dozen_surcharge === '2100',
    );
    await dialog.getByLabel('Recargo de media homogénea').fill('1500');
    await expect(dialog.getByRole('status')).toHaveText(
        'Media homogénea resultante: $ 12.500',
    );
    await page.waitForTimeout(850);
    await expect(dialog.getByRole('status')).toHaveText(
        'Media homogénea resultante: $ 12.500',
    );
    await page.setViewportSize({ width: 320, height: 740 });
    await expect(dialog.getByLabel('Recargo de media homogénea')).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    const bounds = await dialog
        .getByLabel('Recargo de media homogénea')
        .evaluate((input) => ({
            right: input.getBoundingClientRect().right,
            width: input.getBoundingClientRect().width,
        }));
    const dialogBounds = await dialog.boundingBox();
    expect(bounds.right).toBeLessThanOrEqual(
        dialogBounds!.x + dialogBounds!.width,
    );
    expect(bounds.width).toBeGreaterThan(100);
    await page.screenshot({ path: 'storage/app/p0a-surcharge-320.png' });
});
