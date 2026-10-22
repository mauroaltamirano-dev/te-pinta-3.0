import { expect, test } from './support/test';

test('administrator corrects an unused recipe without history and retires it', async ({
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
    const categoryName = `Retiro ${suffix}`;
    const ingredient = `Harina ${suffix}`;
    const output = `Masa salteña ${suffix}`;
    await page.goto('/admin/inventory');
    await page
        .getByRole('button', { name: 'Nueva categoría', exact: true })
        .click();
    const category = page.getByRole('dialog', { name: 'Nueva categoría' });
    await category.getByLabel('Nombre', { exact: true }).fill(categoryName);
    await category.getByRole('button', { name: 'Guardar categoría' }).click();
    await expect(category).toBeHidden();

    for (const [index, name] of [ingredient, output].entries()) {
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
            .selectOption(index ? 'semi_finished' : 'raw_material');
        await item
            .getByLabel('Unidad de ingreso')
            .selectOption({ label: 'Kilogramo' });
        await item
            .getByRole('checkbox', {
                name: index ? 'Salida de producción' : 'Usar en recetas',
                exact: true,
            })
            .check();
        await item.getByRole('button', { name: 'Guardar insumo' }).click();
        await expect(item).toBeHidden();
    }

    await page.goto('/admin/inventory/recipes/create');
    await page.getByLabel('Nombre de la receta').fill(`Salteña ${suffix}`);
    await page.getByLabel('Item producido').selectOption({ label: output });
    await page.getByLabel('Salida esperada', { exact: true }).fill('2');
    await page
        .getByLabel('Ingrediente 1', { exact: true })
        .selectOption({ label: `${ingredient} · ${categoryName}` });
    await page.getByLabel('Cantidad 1', { exact: true }).fill('1');
    await page.getByLabel('Cantidad 1', { exact: true }).press('Enter');
    await expect(page).toHaveURL(/\/admin\/inventory\/recipes\/create$/);
    await page
        .getByRole('button', { name: 'Crear receta', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/inventory\/recipes\/\d+$/);

    await page
        .getByRole('button', { name: 'Corregir receta', exact: true })
        .click();
    await page
        .getByLabel('Nombre de la receta')
        .fill(`Salteña vieja ${suffix}`);
    await page.getByLabel('Salida esperada', { exact: true }).fill('3');
    await page
        .getByRole('button', { name: 'Guardar corrección', exact: true })
        .click();
    await expect(
        page.getByRole('heading', { name: `Salteña vieja ${suffix}` }),
    ).toBeVisible();
    await expect(page.getByRole('article')).toContainText('Versión 1');
    await expect(page.getByRole('article')).toContainText('3 kg');
    await page.getByRole('link', { name: 'Retiradas', exact: true }).click();
    await expect(page.getByText('No hay versiones retiradas.')).toBeVisible();

    page.once('dialog', (dialog) => dialog.accept());
    await page
        .getByRole('button', { name: 'Retirar receta', exact: true })
        .click();
    await expect(
        page.getByRole('status').filter({ hasText: 'Receta retirada' }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'Retiradas', exact: true }).click();
    await expect(page.getByRole('article')).toContainText('Versión 1');
    await expect(
        page.getByRole('button', { name: 'Crear nueva versión' }),
    ).toHaveCount(0);
    await page.goto('/admin/inventory/production/create');
    await expect(page.getByText(`Salteña vieja ${suffix}`)).toHaveCount(0);
});
