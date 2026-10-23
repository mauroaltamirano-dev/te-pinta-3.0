import type { Page } from '@playwright/test';
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

async function createProduct(
    page: Page,
    name: string,
    family: 'empanada' | 'unit',
) {
    await page.goto('/admin/settings/products');
    const initial = JSON.parse(
        (await page.locator('script[data-page="app"]').textContent()) ?? '{}',
    );
    const category = initial.props.categories.find(
        (c: { name: string }) => c.name === 'Clásicas',
    );
    expect(category).toBeTruthy();
    const status = await page.evaluate(
        async ({ name, family, categoryId }) => {
            const token = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((c) => c.startsWith('XSRF-TOKEN='))
                    ?.slice('XSRF-TOKEN='.length) ?? '',
            );
            const body = {
                name,
                pricing_family: family,
                category_id: categoryId,
                is_available: true,
                sort_order: 10,
                // Empanada unit and half-dozen prices are derived from the dozen on the server.
                ...(family === 'empanada'
                    ? { dozen_price: 18000 }
                    : { unit_price: 2500 }),
            };
            const r = await fetch('/admin/settings/products', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': token,
                    Accept: 'text/html',
                },
                body: JSON.stringify(body),
            });

            return r.status;
        },
        { name, family, categoryId: category.id },
    );
    expect(status).toBe(200);
}

/** Desktop shows a table row per sale, phones a card; both carry the same text. */
function saleRows(page: Page) {
    return page.locator(
        '[data-testid="costing-row"]:visible, [data-testid="costing-card"]:visible',
    );
}

async function answerPackaging(
    page: Page,
    sale: string,
    item: string | null,
    quantity = '1',
) {
    const group = page.getByRole('group', {
        name: `¿Qué caja o envoltorio lleva ${sale}?`,
    });

    if (item === null) {
        await group.getByLabel('No lleva', { exact: true }).check();

        return;
    }

    await group.getByLabel('Lleva caja o envoltorio', { exact: true }).check();
    await page
        .getByLabel(`Caja o envoltorio 1 para ${sale}`, { exact: true })
        .selectOption({ label: item });
    await page
        .getByLabel(`Cantidad 1 para ${sale}`, { exact: true })
        .fill(quantity);
}

async function openNewPackaging(page: Page) {
    await page.goto('/admin/inventory/costing-policies');
    const create = page.getByRole('button', {
        name: 'Nuevo packaging y gastos',
        exact: true,
    });

    if (await create.isVisible()) {
        await create.click();
    }
}

test('current costing configures exact packaging, estimates family prices and keeps stock unchanged', async ({
    page,
}, info) => {
    test.setTimeout(120000);
    const name = `Costing ${info.project.name}`;
    const product = `Empanada costing ${info.project.name}`;
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await login(page);
    await createProduct(page, product, 'empanada');
    await page.goto('/admin/inventory');
    await page
        .getByRole('button', { name: 'Nueva categoría', exact: true })
        .click();
    const category = page.getByRole('dialog', { name: 'Nueva categoría' });
    await category.getByLabel('Nombre', { exact: true }).fill(name);
    await category.getByRole('button', { name: 'Guardar categoría' }).click();
    await expect(category).toBeHidden();

    for (const kind of ['semi_finished', 'packaging']) {
        await page
            .getByRole('button', { name: 'Nuevo insumo', exact: true })
            .click();
        const dialog = page.getByRole('dialog', { name: 'Nuevo insumo' });
        await dialog
            .getByLabel('Nombre', { exact: true })
            .fill(`${name} ${kind}`);
        await dialog
            .getByLabel('Categoría', { exact: true })
            .selectOption({ label: name });
        await dialog.getByLabel('Clasificación').selectOption(kind);
        await dialog
            .getByLabel('Tipo de medida')
            .selectOption(kind === 'packaging' ? 'count' : 'weight');
        await dialog.getByLabel('Unidad de ingreso').selectOption({
            label: kind === 'packaging' ? 'Unidad' : 'Kilogramo',
        });

        if (kind === 'semi_finished') {
            await dialog.getByLabel('Consumo al vender').check();
        }

        await dialog.getByRole('button', { name: 'Guardar insumo' }).click();
        await expect(dialog).toBeHidden();
        await page.goto('/admin/management/purchases/create');
        await page.getByLabel('Proveedor', { exact: true }).fill(name);
        await page
            .getByLabel('Insumo 1', { exact: true })
            .selectOption({ label: `${name} ${kind}` });
        await page.getByLabel('Cantidad 1', { exact: true }).fill('1');
        await page
            .getByLabel('Precio 1', { exact: true })
            .fill(kind === 'packaging' ? '700' : '10000');
        await page
            .getByRole('button', { name: 'Confirmar compra', exact: true })
            .click();
        await expect(page).toHaveURL(/\/purchases\/\d+$/);
        await page.goto('/admin/inventory');
    }

    await page.goto('/admin/inventory/costing');
    await page
        .getByLabel('Producto', { exact: true })
        .selectOption({ label: product });
    await page
        .getByRole('link', {
            name: `Indicá qué insumos lleva ${product}.`,
            exact: true,
        })
        .click();
    await page.getByRole('button', { name: 'Agregar componente' }).click();
    await page.getByLabel('Insumo 1', { exact: true }).selectOption({
        label: `${name} semi_finished · ${name} · Semielaborado`,
    });
    await page.getByLabel('Cantidad 1', { exact: true }).fill('55');
    await page
        .getByLabel('Unidad 1', { exact: true })
        .selectOption({ label: 'Gramo' });
    await page.getByRole('button', { name: 'Guardar configuración' }).click();
    await expect(page.getByText('Versión 1 · Actual')).toBeVisible();
    await openNewPackaging(page);
    await page.getByLabel('Nombre', { exact: true }).fill(name);
    await page.getByLabel('Gastos generales (%)').fill('10');
    await answerPackaging(page, 'la unidad', null);
    await answerPackaging(
        page,
        'la media docena',
        `${name} packaging`,
        '1.000000',
    );
    await answerPackaging(page, 'la docena', `${name} packaging`, '1.000000');
    await page.getByRole('button', { name: 'Guardar', exact: true }).click();
    await expect(
        page.getByRole('heading', { name, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText(`La media docena: 1 unidad de ${name} packaging`, {
            exact: true,
        }),
    ).toBeVisible();
    await page
        .getByRole('button', { name: `Editar ${name}`, exact: true })
        .click();
    await page.getByLabel('Gastos generales (%)').fill('25');
    await page.getByRole('button', { name: 'Guardar', exact: true }).click();
    await expect(
        page
            .getByTestId('policy-summary')
            .filter({ hasText: name })
            .getByText('Gastos generales: 25 %', { exact: true }),
    ).toBeVisible();
    await page.goto('/admin/inventory/costing');
    await page
        .getByLabel('Producto', { exact: true })
        .selectOption({ label: product });
    await page
        .getByLabel('¿Qué packaging y gastos usa este producto?')
        .selectOption({ label: name });
    await page.getByRole('button', { name: 'Guardar', exact: true }).click();
    const rows = saleRows(page);
    await expect(rows).toHaveCount(3);
    await expect(rows.nth(0)).toContainText('Unidad');
    await expect(rows.nth(1)).toContainText('Media docena');
    await expect(rows.nth(2)).toContainText('Docena');
    // 55 g at 10 pesos/g = 550, plus 25 % = 687.5, rounded once.
    await expect(rows.nth(0)).toContainText(/688/);
    await expect(page.getByText('Para completar el costo')).toHaveCount(0);
    await expect(rows.filter({ hasText: 'Falta' })).toHaveCount(0);
    await page.getByText('Ver de qué se compone el costo').click();
    await expect(
        page.getByText(`${name} packaging · 1 unidad`).first(),
    ).toBeVisible();
    await expect(page.getByText('1000000 unit', { exact: true })).toHaveCount(
        0,
    );
    const width = await page.evaluate(() => ({
        full: document.documentElement.scrollWidth,
        viewport: innerWidth,
    }));
    expect(width.full).toBeLessThanOrEqual(width.viewport);
    await page.goto('/admin/inventory');
    const inventoryBefore = await page.locator('main').innerText();
    await page.goto('/admin/inventory/costing');
    await page
        .getByLabel('Producto', { exact: true })
        .selectOption({ label: product });
    await page.reload();
    await page.goto('/admin/inventory');
    expect(await page.locator('main').innerText()).toBe(inventoryBefore);
    expect(errors).toEqual([]);
});

test('unit-priced product has one sale presentation and explicit packaging removal', async ({
    page,
}, info) => {
    await login(page);
    const name = `Unit costing ${info.project.name}`;
    await createProduct(page, name, 'unit');
    await openNewPackaging(page);
    await page.getByLabel('Nombre', { exact: true }).fill(name);
    await answerPackaging(page, 'la unidad', null);
    await page.getByRole('button', { name: 'Guardar', exact: true }).click();
    await expect(
        page.getByRole('heading', { name, exact: true }),
    ).toBeVisible();
    await page.goto('/admin/inventory/costing');
    await page
        .getByLabel('Producto', { exact: true })
        .selectOption({ label: name });
    await page
        .getByRole('link', {
            name: `Indicá qué insumos lleva ${name}.`,
            exact: true,
        })
        .click();
    await page
        .getByRole('combobox', { name: 'Estado', exact: true })
        .selectOption('no_inventory');
    await page.getByRole('button', { name: 'Guardar configuración' }).click();
    await page.goto('/admin/inventory/costing');
    await page
        .getByLabel('Producto', { exact: true })
        .selectOption({ label: name });
    const rows = saleRows(page);
    await expect(rows).toHaveCount(1);
    await expect(rows).toContainText('Unidad');
    await page
        .getByLabel('¿Qué packaging y gastos usa este producto?')
        .selectOption({ label: name });
    await page.getByRole('button', { name: 'Guardar', exact: true }).click();
    await expect(rows).not.toContainText('Falta');
    await expect(rows).toContainText(/2[.\s]500/);
    await page
        .getByLabel('¿Qué packaging y gastos usa este producto?')
        .selectOption('');
    await page.getByRole('button', { name: 'Guardar', exact: true }).click();
    await expect(
        page.getByRole('link', {
            name: `Elegí qué packaging y gastos generales usa ${name}.`,
            exact: true,
        }),
    ).toBeVisible();
    await expect(rows).toContainText('Falta');
});
