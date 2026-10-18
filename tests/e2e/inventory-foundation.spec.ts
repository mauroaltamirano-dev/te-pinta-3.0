import { expect, test } from './support/test';

test('administrator configures classified items and exact purchase presentations', async ({
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
    await page.goto('/admin/inventory');
    await expect(
        page.getByRole('heading', { name: 'Inventario físico', exact: true }),
    ).toBeVisible();
    const historyBefore = JSON.parse(
        (await page.locator('script[data-page="app"]').textContent()) ?? '{}',
    ).props.transactions;
    const suffix = test.info().project.name;

    await page
        .getByRole('button', { name: 'Nueva categoría', exact: true })
        .click();
    const category = page.getByRole('dialog', { name: 'Nueva categoría' });
    await category
        .getByLabel('Nombre', { exact: true })
        .fill(`Ingredientes ${suffix}`);
    await category.getByRole('button', { name: 'Guardar categoría' }).click();
    await expect(category).toBeHidden();

    await page
        .getByRole('button', { name: 'Nuevo insumo', exact: true })
        .click();
    const item = page.getByRole('dialog', { name: 'Nuevo insumo' });
    await item
        .getByLabel('Nombre', { exact: true })
        .fill(`Roast beef ${suffix}`);
    await item
        .getByLabel('Categoría', { exact: true })
        .selectOption({ label: `Ingredientes ${suffix}` });
    await item.getByLabel('Clasificación').selectOption('raw_material');
    await item
        .getByLabel('Unidad de ingreso')
        .selectOption({ label: 'Kilogramo' });
    await item.getByLabel('Usar en recetas').check();
    await item.getByRole('button', { name: 'Guardar insumo' }).click();
    await expect(item).toBeHidden();
    const card = page.getByRole('article', { name: `Roast beef ${suffix}` });
    await expect(card).toContainText('0 g');
    await expect(card).toContainText('Usar en recetas');

    await card.getByRole('button', { name: 'Presentaciones' }).click();
    const presentation = page.getByRole('dialog', {
        name: `Presentaciones: Roast beef ${suffix}`,
    });
    await presentation.getByLabel('Nombre', { exact: true }).fill('Bolsa');
    await presentation
        .getByLabel('Contenido', { exact: true })
        .fill('1.000125');
    await presentation
        .getByLabel('Unidad de contenido')
        .selectOption({ label: 'Kilogramo' });
    await presentation
        .getByRole('button', { name: 'Guardar presentación' })
        .click();
    await expect(presentation).toBeHidden();
    await expect(card).toContainText('1,000125 kg');

    await card.getByRole('button', { name: 'Editar insumo' }).click();
    const edit = page.getByRole('dialog', { name: 'Editar insumo' });
    await edit
        .getByLabel('Nombre', { exact: true })
        .fill(`Roast beef editado ${suffix}`);
    await edit.getByRole('button', { name: 'Guardar insumo' }).click();
    await expect(edit).toBeHidden();
    await expect(
        page.getByRole('article', { name: `Roast beef editado ${suffix}` }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Historial físico' }),
    ).toBeVisible();
    await page.reload();
    const historyAfter = JSON.parse(
        (await page.locator('script[data-page="app"]').textContent()) ?? '{}',
    ).props.transactions;
    // Configuration must preserve both existing history and an initially empty history.
    expect(historyAfter).toEqual(historyBefore);
    await test.info().attach('inventory-history-invariant', {
        body: JSON.stringify({ before: historyBefore, after: historyAfter }),
        contentType: 'application/json',
    });

    if (historyBefore.total === 0) {
        await expect(
            page.getByText('Todavía no hay movimientos físicos.'),
        ).toBeVisible();
    }

    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBe(true);
    await page.screenshot({
        path: `storage/app/aob-inventory-${suffix}.png`,
        fullPage: true,
    });
});
