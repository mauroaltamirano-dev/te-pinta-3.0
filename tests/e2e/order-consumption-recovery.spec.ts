import { expect, test } from '@playwright/test';

test('administrator versions physical consumption and fulfills an order once', async ({
    page,
}) => {
    test.setTimeout(120000);
    const name = `Sale filling ${test.info().project.name}`;
    const productName =
        test.info().project.name === 'chromium' ? 'Criolla Dulce' : 'Caprese';
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
    await page
        .getByRole('button', { name: 'Nuevo insumo', exact: true })
        .click();
    const item = page.getByRole('dialog', { name: 'Nuevo insumo' });
    await item.getByLabel('Nombre', { exact: true }).fill(name);
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
    await page.goto('/admin/management/purchases/create');
    await page.getByLabel('Proveedor', { exact: true }).fill(name);
    await page
        .getByLabel('Insumo', { exact: true })
        .selectOption({ label: name });
    await page.getByLabel('Cantidad', { exact: true }).fill('1');
    await page
        .getByLabel('Precio en pesos enteros', { exact: true })
        .fill('10000');
    await page
        .getByRole('button', { name: 'Confirmar compra', exact: true })
        .click();
    await expect(page).toHaveURL(/\/purchases\/\d+$/);
    await page.goto('/admin/settings/products');
    const card = page.locator('[data-slot="card"]').filter({
        has: page.getByRole('heading', { name: productName, exact: true }),
    });
    await card.getByRole('link', { name: 'Configurar consumo físico' }).click();
    await expect(page).toHaveURL(/\/inventory\/product-consumption\/\d+$/);
    const productId = Number(page.url().split('/').pop());
    await expect(
        page.getByText('Falta configuración:', { exact: false }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Agregar componente' }).click();
    await page
        .getByLabel('Item 1', { exact: true })
        .selectOption({ label: `${name} · ${name} · semi_finished` });
    await page.getByLabel('Cantidad 1', { exact: true }).fill('55.000000');
    await page
        .getByLabel('Unidad 1', { exact: true })
        .selectOption({ label: 'g' });
    await page.getByRole('button', { name: 'Guardar configuración' }).click();
    await expect(page.getByText('Versión 1 · Actual')).toBeVisible();
    await expect(page.locator('article')).toContainText('55 g');
    await page.getByLabel('Cantidad 1', { exact: true }).fill('60');
    await page.getByRole('button', { name: 'Guardar configuración' }).click();
    await expect(page.getByText('Versión 2 · Actual')).toBeVisible();
    await page.getByRole('button', { name: 'Retiradas', exact: true }).click();
    await expect(page.locator('article')).toContainText('Versión 1 · Retirada');
    await expect(page.locator('article')).toContainText('55 g');
    // Exercise the existing commercial and fulfillment endpoints, not a future POS lifecycle.
    const orderUrl = await page.evaluate(
        async ({ productId, name }) => {
            const token = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((c) => c.startsWith('XSRF-TOKEN='))
                    ?.slice('XSRF-TOKEN='.length) ?? '',
            );
            const r = await fetch('/admin/orders', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': token,
                    Accept: 'text/html',
                },
                body: JSON.stringify({
                    items: [{ product_id: productId, quantity: 3 }],
                    addons: [],
                    condition: 'cooked',
                    delivery_type: 'pickup',
                    delivery_date: new Date().toISOString().slice(0, 10),
                    delivery_period: 'night',
                    customer: { name },
                    payment_status: 'unpaid',
                }),
            });

            if (!r.ok || !/\/orders\/TP-/.test(r.url)) {
                throw new Error(`Order creation failed: ${r.status} ${r.url}`);
            }

            return r.url;
        },
        { productId, name },
    );
    await page.goto(orderUrl);
    async function status(value: string) {
        return page.evaluate(
            async ({ orderUrl, value }) => {
                const token = decodeURIComponent(
                    document.cookie
                        .split('; ')
                        .find((c) => c.startsWith('XSRF-TOKEN='))
                        ?.slice('XSRF-TOKEN='.length) ?? '',
                );
                const r = await fetch(`${orderUrl}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': token,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ _method: 'PATCH', status: value }),
                });

                return r.status;
            },
            { orderUrl, value },
        );
    }
    expect(await status('delivered')).toBe(200);
    expect(await status('delivered')).toBe(200);
    await page.goto('/admin/inventory');
    const row = page.getByRole('article', { name, exact: true });
    await expect(row).toContainText('820 g');
    expect(await status('cancelled')).toBe(200);
    expect(await status('cancelled')).toBe(200);
    await page.reload();
    await expect(row).toContainText('1.000 g');
    expect(await status('delivered')).toBe(422);
    await page.goto(`/admin/inventory/product-consumption/${productId}`);
    await expect(page.getByText('Versión 2 · Actual')).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);

    if (test.info().project.name !== 'chromium') {
        await page.screenshot({
            path: 'storage/app/aob-runtime/aob5-mobile.png',
            fullPage: true,
        });
    }
});
