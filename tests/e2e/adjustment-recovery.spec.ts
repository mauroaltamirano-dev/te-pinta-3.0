import { expect, test } from '@playwright/test';
test('administrator previews a physical count, records loss and reverses exact history', async ({
    page,
}) => {
    test.setTimeout(120000);
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
    const name = `Ajustes cebolla ${test.info().project.name}`;
    await page.goto('/admin/inventory');
    await expect(
        page.getByRole('heading', { name: 'Inventario físico', exact: true }),
    ).toBeVisible();
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
    await item.getByLabel('Clasificación').selectOption('raw_material');
    await item
        .getByLabel('Unidad de ingreso')
        .selectOption({ label: 'Kilogramo' });
    await item.getByRole('button', { name: 'Guardar insumo' }).click();
    await expect(item).toBeHidden();
    await page.goto('/admin/management/purchases/create');
    await page.getByLabel('Proveedor', { exact: true }).fill(name);
    await page
        .getByLabel('Insumo', { exact: true })
        .selectOption({ label: name });
    await page.getByLabel('Cantidad', { exact: true }).fill('12');
    await page
        .getByLabel('Precio en pesos enteros', { exact: true })
        .fill('48000');
    await page
        .getByRole('button', { name: 'Confirmar compra', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/management\/purchases\/\d+$/);
    await page.goto('/admin/inventory/adjustments');
    if (test.info().project.name === 'chromium') {
        await expect(
            page.getByText('No hay ajustes registrados.', { exact: false }),
        ).toBeVisible();
    }
    await expect(
        page.getByRole('heading', {
            name: 'Historial de ajustes',
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByText('Registrar una merma modifica inventario', {
            exact: false,
        }),
    ).toBeVisible();
    await page
        .getByLabel('Item', { exact: true })
        .selectOption({ label: `${name} · ${name}` });
    await page
        .getByLabel('Conteo físico observado', { exact: true })
        .fill('10.5');
    await page
        .getByRole('button', { name: 'Ver vista previa', exact: true })
        .click();
    const preview = page.getByRole('region', {
        name: 'Vista previa del ajuste',
    });
    await expect(preview).toContainText('-1.500 g');
    await expect(preview).toContainText('10.500 g');
    await expect(preview).toContainText('6.000');
    await page
        .getByLabel('Conteo físico observado', { exact: true })
        .fill('11');
    await expect(preview).toHaveCount(0);
    await expect(
        page.getByRole('button', { name: 'Confirmar ajuste', exact: true }),
    ).toHaveCount(0);
    await page
        .getByLabel('Conteo físico observado', { exact: true })
        .fill('10.5');
    await page
        .getByRole('button', { name: 'Ver vista previa', exact: true })
        .click();
    await expect(preview).toBeVisible();
    await page
        .getByRole('button', { name: 'Confirmar ajuste', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/inventory\/adjustments\/\d+$/);
    const countUrl = page.url();
    await expect(page.getByRole('article')).toContainText('6.000');
    await expect(page.getByRole('article')).toContainText(
        'Conteo físico observado',
    );
    await page.goto('/admin/inventory/adjustments');
    await page
        .getByLabel('Item', { exact: true })
        .selectOption({ label: `${name} · ${name}` });
    await page.getByLabel('Tipo de ajuste').selectOption('waste');
    await expect(
        page.getByLabel('Costo total explícito (pesos enteros)'),
    ).toHaveCount(0);
    await page.getByLabel('Cantidad perdida', { exact: true }).fill('2');
    await page
        .getByRole('button', { name: 'Ver vista previa', exact: true })
        .click();
    await expect(preview).toContainText('8.000');
    await page
        .getByRole('button', { name: 'Confirmar ajuste', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/inventory\/adjustments\/\d+$/);
    await expect(page.getByRole('heading', { level: 1 })).toContainText(
        'Merma',
    );
    const wasteUrl = page.url();
    await page
        .getByRole('button', { name: 'Revertir ajuste', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Revertir ajuste' });
    await dialog
        .getByLabel('Motivo de reversión')
        .fill('Lote contado dos veces');
    await dialog
        .getByRole('button', { name: 'Confirmar reversión', exact: true })
        .click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('Revertido:', { exact: false })).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Revertir ajuste', exact: true }),
    ).toHaveCount(0);
    await expect(page.getByRole('article')).toContainText('8.000');
    await page
        .getByRole('link', { name: 'Ver movimiento histórico en Inventario' })
        .click();
    await expect(
        page.getByRole('article', { name, exact: true }),
    ).toContainText('10.500 g');
    await page.goto(countUrl);
    await page
        .getByRole('button', { name: 'Revertir ajuste', exact: true })
        .click();
    await dialog
        .getByLabel('Motivo de reversión')
        .fill('Restaurar conteo original');
    await dialog
        .getByRole('button', { name: 'Confirmar reversión', exact: true })
        .click();
    await expect(dialog).toBeHidden();
    await page
        .getByRole('link', { name: 'Ver movimiento histórico en Inventario' })
        .click();
    await expect(
        page.getByRole('article', { name, exact: true }),
    ).toContainText('12.000 g');

    for (const url of [countUrl, wasteUrl, '/admin/inventory/adjustments']) {
        await page.goto(url);
        const width = await page.evaluate(() => ({
            content: document.documentElement.scrollWidth,
            screen: window.innerWidth,
        }));
        expect(width.content).toBeLessThanOrEqual(width.screen);
    }

    await page.screenshot({
        path: `storage/app/aob-runtime/aob4-${test.info().project.name}.png`,
        fullPage: true,
    });
});
