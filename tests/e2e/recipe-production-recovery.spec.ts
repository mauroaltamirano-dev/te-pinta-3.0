import { expect, test } from './support/test';

test('administrator versions a recipe, transfers actual production cost and reverses history', async ({
    page,
}) => {
    test.setTimeout(120_000);
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
    const suffix = test.info().project.name;
    const categoryName = `Producción ${suffix}`;
    const names = [
        `Cebolla ${suffix}`,
        `Roast Beef ${suffix}`,
        `Relleno Criolla Dulce ${suffix}`,
    ];
    await page.goto('/admin/inventory');
    await page
        .getByRole('button', { name: 'Nueva categoría', exact: true })
        .click();
    const category = page.getByRole('dialog', { name: 'Nueva categoría' });
    await category.getByLabel('Nombre', { exact: true }).fill(categoryName);
    await category.getByRole('button', { name: 'Guardar categoría' }).click();
    await expect(category).toBeHidden();

    for (const [index, name] of names.entries()) {
        await page
            .getByRole('button', { name: 'Nuevo insumo', exact: true })
            .click();
        const item = page.getByRole('dialog', { name: 'Nuevo insumo' });
        await item.getByLabel('Nombre', { exact: true }).fill(name);
        await item
            .getByLabel('Categoría', { exact: true })
            .selectOption({ label: categoryName });
        await item
            .getByLabel('Clasificación')
            .selectOption(index === 2 ? 'semi_finished' : 'raw_material');
        await item
            .getByLabel('Unidad de ingreso')
            .selectOption({ label: 'Kilogramo' });
        await item
            .getByRole('checkbox', {
                name: index === 2 ? 'Salida de producción' : 'Usar en recetas',
                exact: true,
            })
            .check();
        await item.getByRole('button', { name: 'Guardar insumo' }).click();
        await expect(item).toBeHidden();
    }

    for (const [index, price] of [2150, 14500].entries()) {
        await page.goto('/admin/management/purchases/create');
        await page.getByLabel('Proveedor', { exact: true }).fill(categoryName);
        await page
            .getByLabel('Insumo', { exact: true })
            .selectOption({ label: names[index] });
        await page.getByLabel('Cantidad', { exact: true }).fill('1');
        await page
            .getByLabel('Precio en pesos enteros', { exact: true })
            .fill(String(price));
        await page
            .getByRole('button', { name: 'Confirmar compra', exact: true })
            .click();
        await expect(page).toHaveURL(/\/admin\/management\/purchases\/\d+$/);
    }

    await page.goto('/admin/inventory/recipes/create');
    await page.getByLabel('Nombre de la receta').fill(names[2]);
    await page.getByLabel('Item producido').selectOption({ label: names[2] });
    await page.getByLabel('Salida esperada', { exact: true }).fill('2');

    for (let i = 0; i < 2; i++) {
        if (i) {
            await page
                .getByRole('button', { name: 'Agregar ingrediente' })
                .click();
        }

        await page
            .getByLabel(`Ingrediente ${i + 1}`, { exact: true })
            .selectOption({ label: `${names[i]} · ${categoryName}` });
        await page.getByLabel(`Cantidad ${i + 1}`, { exact: true }).fill('1');
    }

    await page
        .getByRole('button', { name: 'Crear receta', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/inventory\/recipes\/\d+$/);
    const recipeUrl = page.url();
    await expect(page.getByRole('article')).toContainText('16.650');
    await expect(page.getByRole('article')).toContainText('8.325');
    await page
        .getByRole('button', { name: 'Crear nueva versión', exact: true })
        .click();
    await page
        .getByRole('button', { name: 'Guardar nueva versión', exact: true })
        .click();
    await expect(page.getByRole('article')).toHaveCount(1);
    await expect(page.getByRole('article')).toContainText('Versión 2');
    await page.getByRole('link', { name: 'Retiradas', exact: true }).click();
    await expect(page.getByRole('article')).toHaveCount(1);
    await expect(page.getByRole('article')).toContainText('Versión 1');
    await expect(
        page.getByRole('link', { name: 'Registrar producción', exact: true }),
    ).toHaveCount(0);
    await page.getByRole('link', { name: 'Actual', exact: true }).click();
    await page
        .getByRole('link', { name: 'Registrar producción', exact: true })
        .click();
    await page.getByLabel('Cantidad real producida').fill('1.85');
    await page
        .getByRole('button', { name: 'Confirmar producción', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/inventory\/production\/\d+$/);
    const batchUrl = page.url();
    await expect(page.getByRole('article')).toContainText('16.650');
    await expect(page.getByRole('article')).toContainText('9.000');
    await expect(page.getByRole('article')).toContainText('1,85 kg');
    await page
        .getByRole('link', { name: 'Ver movimientos originales en Inventario' })
        .click();
    await expect(
        page.getByRole('article', { name: names[2], exact: true }),
    ).toContainText('1.850 g');
    await page.goto(batchUrl);
    await page
        .getByRole('button', { name: 'Revertir producción', exact: true })
        .click();
    const reversal = page.getByRole('dialog', { name: 'Revertir producción' });
    await reversal.getByLabel('Motivo', { exact: true }).fill('Lote duplicado');
    await reversal
        .getByRole('button', { name: 'Confirmar reversión', exact: true })
        .click();
    await expect(reversal).toBeHidden();
    await expect(page.getByText('Revertido:', { exact: false })).toContainText(
        'Lote duplicado',
    );
    await expect(page.getByRole('article')).toContainText('16.650');
    await page
        .getByRole('link', { name: 'Ver movimientos originales en Inventario' })
        .click();
    await expect(
        page.getByRole('article', { name: names[2], exact: true }),
    ).toContainText('0 g');

    for (const url of [recipeUrl, batchUrl, '/admin/inventory/production']) {
        await page.goto(url);
        const width = await page.evaluate(() => ({
            content: document.documentElement.scrollWidth,
            screen: window.innerWidth,
        }));
        expect(width.content).toBeLessThanOrEqual(width.screen);
    }

    await page.screenshot({
        path: `storage/app/aob-runtime/aob3-${suffix}.png`,
        fullPage: true,
    });
});
