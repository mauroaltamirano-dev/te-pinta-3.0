import { expect, test } from './support/test';
test('administrator records and reverses a rich purchase through its canonical route', async ({
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
    const name = `Cebolla compras ${test.info().project.name}`;
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
    await item.getByLabel('Clasificación').selectOption('raw_material');
    await item
        .getByLabel('Unidad de ingreso')
        .selectOption({ label: 'Kilogramo' });
    await item.getByRole('button', { name: 'Guardar insumo' }).click();
    await expect(item).toBeHidden();
    await page.goto('/admin/management/purchases');
    await page
        .getByRole('link', { name: 'Registrar compra', exact: true })
        .click();
    await page.getByLabel('Proveedor', { exact: true }).fill(name);
    await page
        .getByLabel('Insumo', { exact: true })
        .selectOption({ label: name });
    await page.getByLabel('Cantidad', { exact: true }).fill('2');
    await page
        .getByLabel('Precio en pesos enteros', { exact: true })
        .fill('4300');
    await page
        .getByLabel('Envío en pesos enteros', { exact: true })
        .fill('300');
    await page
        .getByRole('button', { name: 'Confirmar compra', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/management\/purchases\/\d+$/);
    await expect(
        page.getByRole('heading', { name: 'Impacto registrado' }),
    ).toBeVisible();
    await expect(
        page.getByText('Egreso total:', { exact: false }),
    ).toContainText('4.600');
    const detail = page.url();
    await page
        .getByRole('link', { name: 'Ver entrada física original' })
        .click();
    await expect(page.getByRole('article', { name })).toContainText('2.000 g');
    await page.goto(detail);
    await page
        .getByRole('button', { name: 'Revertir compra', exact: true })
        .click();
    const reversal = page.getByRole('alertdialog', { name: 'Revertir compra' });
    await reversal.getByLabel('Motivo').fill('Factura duplicada');
    await reversal.getByRole('button', { name: 'Confirmar reversión' }).click();
    await expect(reversal).toBeHidden();
    await expect(
        page.getByText('Factura duplicada', { exact: false }),
    ).toBeVisible();
    await page
        .getByRole('link', { name: 'Ver entrada física original' })
        .click();
    await expect(page.getByRole('article', { name })).toContainText('0 g');
    const width = await page.evaluate(() => ({
        content: document.documentElement.scrollWidth,
        screen: window.innerWidth,
    }));
    expect(width.content).toBeLessThanOrEqual(width.screen);
});
