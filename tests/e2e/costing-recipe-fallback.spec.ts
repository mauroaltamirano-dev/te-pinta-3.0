import type { Page } from '@playwright/test';
import { seedRecipeBackedEmpanada } from './support/costing-fixture';
import { expect, test } from './support/test';

async function login(page: Page) {
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
}

function sale(page: Page, name: string) {
    return page
        .locator(
            '[data-testid="costing-row"]:visible, [data-testid="costing-card"]:visible',
        )
        .filter({ hasText: new RegExp(`^${name}`) });
}

async function openCosting(page: Page, product: string) {
    await page.goto('/admin/inventory/costing');
    await page
        .getByLabel('Producto', { exact: true })
        .selectOption({ label: product });
    await expect(page.getByRole('heading', { name: product })).toBeVisible();
}

test('costing estimates an unproduced filling by recipe and turns gaps into actions', async ({
    page,
}, info) => {
    test.setTimeout(120000);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const names = seedRecipeBackedEmpanada(info.project.name);
    await login(page);
    await openCosting(page, names.product);

    // Filling 40 g at 8.5 pesos/g by recipe (340) + wrapper 150 = 490, plus 10 % = 539.
    await expect(sale(page, 'Unidad')).toContainText(/1[.\s]700/);
    await expect(sale(page, 'Unidad')).toContainText(/539/);
    await expect(sale(page, 'Unidad')).toContainText(/1[.\s]161/);
    await expect(sale(page, 'Unidad')).toContainText('68,29 %');
    await expect(sale(page, 'Docena')).toContainText('Falta');
    await expect(page.getByTestId('recipe-estimate-note')).toContainText(
        `Estimado por receta: ${names.filling}`,
    );
    const main = page.locator('main');

    for (const jargon of [
        'política',
        'cobertura',
        'valoración',
        'COGS',
        'basis points',
    ]) {
        await expect(main).not.toContainText(new RegExp(jargon, 'i'));
    }

    const width = await page.evaluate(() => ({
        full: document.documentElement.scrollWidth,
        viewport: innerWidth,
    }));
    expect(width.full).toBeLessThanOrEqual(width.viewport);

    await page
        .getByRole('link', {
            name: `Registrá una compra de ${names.box}.`,
            exact: true,
        })
        .click();
    await expect(page).toHaveURL(/\/admin\/management\/purchases\/create$/);
    await page.getByLabel('Proveedor', { exact: true }).fill('Cartonera');
    await page
        .getByLabel('Insumo 1', { exact: true })
        .selectOption({ label: names.box });
    await page.getByLabel('Cantidad 1', { exact: true }).fill('10');
    await page.getByLabel('Precio 1', { exact: true }).fill('7000');
    await page
        .getByRole('button', { name: 'Confirmar compra', exact: true })
        .click();
    await expect(page).toHaveURL(/\/purchases\/\d+$/);

    // Dozen: 480 g filling 4,080 + 12 wrappers 1,800 + box 700 = 6,580, plus 10 % = 7,238.
    await openCosting(page, names.product);
    await expect(sale(page, 'Docena')).toContainText(/7[.\s]238/);
    await expect(sale(page, 'Docena')).toContainText(/10[.\s]762/);
    await expect(page.getByText('Para completar el costo')).toHaveCount(0);

    await page
        .getByRole('link', { name: `Editar «${names.policy}»`, exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: `Editar ${names.policy}` }),
    ).toBeVisible();
    await expect(page.getByText('Presentación 1')).toHaveCount(0);
    await expect(page.getByText('Unidades comerciales')).toHaveCount(0);
    await expect(page.getByLabel('Gastos generales (%)')).toHaveValue('10');
    await expect(page.getByLabel('Cantidad 1 para la docena')).toHaveValue('1');
    await page.getByLabel('Gastos generales (%)').fill('12,5');
    const halfDozen = page.getByRole('group', {
        name: '¿Qué caja o envoltorio lleva la media docena?',
    });
    await halfDozen
        .getByLabel('Lleva caja o envoltorio', { exact: true })
        .check();
    await page
        .getByLabel('Caja o envoltorio 1 para la media docena', { exact: true })
        .selectOption({ label: names.box });
    await page.getByRole('button', { name: 'Guardar', exact: true }).click();
    const summary = page
        .getByTestId('policy-summary')
        .filter({ hasText: names.policy });
    await expect(summary).toContainText('Gastos generales: 12,5 %');
    await expect(summary).toContainText(
        `La media docena: 1 unidad de ${names.box}`,
    );

    // Half dozen: 240 g filling 2,040 + 6 wrappers 900 + box 700 = 3,640, plus 12.5 % = 4,095.
    await openCosting(page, names.product);
    await expect(sale(page, 'Media docena')).toContainText(/4[.\s]095/);
    expect(errors).toEqual([]);
});
