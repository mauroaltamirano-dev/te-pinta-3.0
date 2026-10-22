import type { Page } from '@playwright/test';
import { expect, test } from './support/test';

// Optional before/after evidence: set SCREEN_DIR to keep full-page captures.
async function capture(page: Page, name: string): Promise<void> {
    if (process.env.SCREEN_DIR) {
        await page.screenshot({
            path: `${process.env.SCREEN_DIR}/after-${name}-${test.info().project.name}.png`,
            fullPage: true,
        });
    }
}

async function signInWithItems(
    page: Page,
    name: string,
    others: string[] = [],
): Promise<void> {
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
    await page.goto('/admin/inventory');
    await page
        .getByRole('button', { name: 'Nueva categoría', exact: true })
        .click();
    const category = page.getByRole('dialog', { name: 'Nueva categoría' });
    await category.getByLabel('Nombre', { exact: true }).fill(name);
    await category.getByRole('button', { name: 'Guardar categoría' }).click();
    await expect(category).toBeHidden();

    for (const itemName of [name, ...others]) {
        await page
            .getByRole('button', { name: 'Nuevo insumo', exact: true })
            .click();
        const item = page.getByRole('dialog', { name: 'Nuevo insumo' });
        await item.getByLabel('Nombre', { exact: true }).fill(itemName);
        await item
            .getByLabel('Categoría', { exact: true })
            .selectOption({ label: name });
        await item.getByLabel('Clasificación').selectOption('semi_finished');
        await item
            .getByLabel('Unidad de ingreso')
            .selectOption({ label: 'Kilogramo' });
        await item.getByLabel('Consumo al vender').check();
        await item.getByRole('button', { name: 'Guardar insumo' }).click();
        await expect(item).toBeHidden();
    }
}

test('purchase lines show subtotals and a summary that adds shipping', async ({
    page,
}) => {
    test.setTimeout(120_000);
    const name = `Relleno compra ${test.info().project.name}`;
    const other = `Harina compra ${test.info().project.name}`;
    await signInWithItems(page, name, [other]);
    await page.goto('/admin/management/purchases/create');
    await expect(page.getByText('capas de costo')).toHaveCount(0);
    await expect(page.getByText('pesos enteros')).toHaveCount(0);
    await page.getByLabel('Proveedor', { exact: true }).fill('Distribuidora');
    await page
        .getByLabel('Insumo 1', { exact: true })
        .selectOption({ label: name });
    await page.getByLabel('Cantidad 1', { exact: true }).fill('2');
    await page
        .getByLabel('Tipo de precio 1', { exact: true })
        .selectOption({ label: 'Por kilogramo' });
    await page.getByLabel('Precio 1', { exact: true }).fill('4300');
    await expect(page.getByLabel('Subtotal 1')).toHaveText(/8\.600/);
    await page.getByRole('button', { name: 'Agregar insumo' }).click();
    await page
        .getByLabel('Insumo 2', { exact: true })
        .selectOption({ label: other });
    await page
        .getByLabel('Presentación 2', { exact: true })
        .selectOption({ label: 'Gramo' });
    await page.getByLabel('Cantidad 2', { exact: true }).fill('500');
    await page.getByLabel('Precio 2', { exact: true }).fill('1200');
    await expect(page.getByLabel('Subtotal 2')).toHaveText(/1\.200/);
    await page.getByLabel('Envío', { exact: true }).fill('300');
    const summary = page.getByRole('region', { name: 'Resumen de la compra' });
    await expect(summary.getByTestId('purchase-items-total')).toHaveText(
        /9\.800/,
    );
    await expect(summary.getByTestId('purchase-total')).toHaveText(/10\.100/);
    await expect(
        summary.getByRole('button', { name: 'Confirmar compra' }),
    ).toBeInViewport();
    // The mobile bottom navigation must not cover the confirm button.
    await page.getByLabel('Insumo 1', { exact: true }).scrollIntoViewIfNeeded();
    const confirm = summary.getByRole('button', { name: 'Confirmar compra' });
    await expect(confirm).toBeInViewport();
    expect(
        await confirm.evaluate((button) => {
            const box = button.getBoundingClientRect();

            return button.contains(
                document.elementFromPoint(
                    box.x + box.width / 2,
                    box.y + box.height / 2,
                ),
            );
        }),
    ).toBe(true);
    await capture(page, 'purchase');
    await summary.getByRole('button', { name: 'Confirmar compra' }).click();
    await expect(page).toHaveURL(/\/admin\/management\/purchases\/\d+$/);
    await expect(
        page.getByText('Egreso total:', { exact: false }),
    ).toContainText('10.100');
});

test('consumption is entered per sold unit with a live dozen example', async ({
    page,
}) => {
    test.setTimeout(120_000);
    const name = `Relleno consumo ${test.info().project.name}`;
    const productName =
        test.info().project.name === 'chromium' ? 'Criolla Dulce' : 'Caprese';
    await signInWithItems(page, name);
    await page.goto('/admin/settings/products');
    await page
        .locator('[data-slot="card"]')
        .filter({
            has: page.getByRole('heading', { name: productName, exact: true }),
        })
        .getByRole('link', { name: 'Configurar consumo físico' })
        .click();
    await expect(
        page.getByText('1 unidad vendida', { exact: false }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Agregar componente' }).click();
    await page
        .getByLabel('Insumo 1', { exact: true })
        .selectOption({ label: `${name} · ${name} · Semielaborado` });
    await page.getByLabel('Cantidad 1', { exact: true }).fill('60');
    await page
        .getByLabel('Unidad 1', { exact: true })
        .selectOption({ label: 'Gramo' });
    await expect(page.getByTestId('consumption-example-1')).toHaveText(
        'Una docena descuenta 12 × 60 g = 720 g',
    );
    await capture(page, 'consumption');
    await page.getByRole('button', { name: 'Guardar configuración' }).click();
    await expect(page.getByText('Versión 1 · Actual')).toBeVisible();
    await expect(page.locator('article')).toContainText('60 g');
});
