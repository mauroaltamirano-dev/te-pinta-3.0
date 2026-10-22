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
        .selectOption({ label: `${product} · Empanada` });
    await expect(
        page.getByText('Configurar consumo físico.', { exact: true }).first(),
    ).toBeVisible();
    await page
        .getByRole('link', { name: 'Configurar consumo físico', exact: true })
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
    await page.goto('/admin/inventory/costing-policies');
    await page.getByLabel('Nombre de la política').fill(name);
    await page
        .getByLabel('Costos indirectos estimados (basis points)')
        .fill('1000');

    for (const [index, quantity] of [1, 6, 12].entries()) {
        const n = index + 1;
        await page
            .getByRole('button', { name: 'Agregar presentación', exact: true })
            .click();
        await page
            .getByLabel(`Unidades comerciales ${n}`, { exact: true })
            .fill(String(quantity));

        if (quantity !== 1) {
            await page
                .getByLabel(`Packaging ${n}`, { exact: true })
                .selectOption('components');
            await page
                .getByRole('button', {
                    name: `Agregar item a presentación ${n}`,
                    exact: true,
                })
                .click();
            await page
                .getByLabel(`Item ${n}.1`, { exact: true })
                .selectOption({ label: `${name} packaging` });
            await page
                .getByLabel(`Cantidad ${n}.1`, { exact: true })
                .fill('1.000000');
        }
    }

    await page
        .getByRole('button', { name: 'Guardar política', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: `${name} · Activa`, exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText(`${name} packaging · 1 unidad`, { exact: true }).first(),
    ).toBeVisible();
    await page
        .getByRole('button', { name: `Editar ${name}`, exact: true })
        .click();
    await page
        .getByLabel('Costos indirectos estimados (basis points)')
        .fill('2500');
    await page
        .getByRole('button', { name: 'Guardar política', exact: true })
        .click();
    await expect(
        page.getByText('Costos indirectos: 25 %', { exact: true }).first(),
    ).toBeVisible();
    await page.goto('/admin/inventory/costing');
    await page
        .getByLabel('Producto', { exact: true })
        .selectOption({ label: `${product} · Empanada` });
    await page
        .getByLabel('Política asignada al producto')
        .selectOption({ label: name });
    await page.getByRole('button', { name: 'Guardar asignación' }).click();
    const cards = page.getByTestId('economics-presentation');
    await expect(cards).toHaveCount(3);
    await expect(
        page.getByRole('heading', { name: 'Unidad', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Media docena', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Docena', exact: true }),
    ).toBeVisible();
    await expect(page.getByText('Costo completo', { exact: true })).toHaveCount(
        3,
    );
    await expect(cards.first()).toContainText(/550/);
    await cards
        .nth(1)
        .getByText('Componentes de packaging', { exact: true })
        .click();
    await expect(cards.nth(1)).toContainText('1 unidad');
    await expect(page.getByText('1000000 unit', { exact: true })).toHaveCount(
        0,
    );
    const width = await page.evaluate(() => ({
        full: document.documentElement.scrollWidth,
        viewport: innerWidth,
    }));
    expect(width.full).toBeLessThanOrEqual(width.viewport);
    await page.screenshot({
        path: `storage/app/aob6-costing-${info.project.name}.png`,
        fullPage: true,
    });
    await page
        .getByRole('link', { name: 'Administrar productos', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/settings\/products$/);
    await page.goto('/admin/inventory');
    const inventoryBefore = await page.locator('main').innerText();
    await page.goto('/admin/inventory/costing');
    await page
        .getByLabel('Producto', { exact: true })
        .selectOption({ label: `${product} · Empanada` });
    await page.reload();
    await page.goto('/admin/inventory');
    expect(await page.locator('main').innerText()).toBe(inventoryBefore);
    expect(errors).toEqual([]);
});

test('unit-priced product has one sale presentation and explicit policy removal', async ({
    page,
}, info) => {
    await login(page);
    const name = `Unit costing ${info.project.name}`;
    await createProduct(page, name, 'unit');
    await page.goto('/admin/inventory/costing-policies');
    await page.getByLabel('Nombre de la política').fill(name);
    await page
        .getByRole('button', { name: 'Agregar presentación', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Guardar política', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: `${name} · Activa`, exact: true }),
    ).toBeVisible();
    await page.goto('/admin/inventory/costing');
    await page
        .getByLabel('Producto', { exact: true })
        .selectOption({ label: `${name} · Unidad` });
    await page
        .getByRole('link', { name: 'Configurar consumo físico', exact: true })
        .click();
    await page
        .getByRole('combobox', { name: 'Estado', exact: true })
        .selectOption('no_inventory');
    await page.getByRole('button', { name: 'Guardar configuración' }).click();
    await page.goto('/admin/inventory/costing');
    await page
        .getByLabel('Producto', { exact: true })
        .selectOption({ label: `${name} · Unidad` });
    await expect(page.getByTestId('economics-presentation')).toHaveCount(1);
    await expect(
        page.getByRole('heading', { name: 'Media docena', exact: true }),
    ).toHaveCount(0);
    await expect(
        page.getByRole('heading', { name: 'Docena', exact: true }),
    ).toHaveCount(0);
    await page
        .getByLabel('Política asignada al producto')
        .selectOption({ label: name });
    await page.getByRole('button', { name: 'Guardar asignación' }).click();
    await expect(
        page.getByText('Costo completo', { exact: true }),
    ).toBeVisible();
    await expect(page.getByTestId('economics-presentation')).toContainText(
        /2[.\s]500/,
    );
    await page.getByLabel('Política asignada al producto').selectOption('');
    await page.getByRole('button', { name: 'Guardar asignación' }).click();
    await expect(
        page.getByText('Sin política asignada', { exact: false }),
    ).toBeVisible();
    await expect(
        page.getByText('Asignar una política de costo.', { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText('Costo incompleto', { exact: true }),
    ).toBeVisible();
});
