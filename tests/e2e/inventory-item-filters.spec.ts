import type { Page } from '@playwright/test';
import { expect, test } from './support/test';

async function createCategory(page: Page, name: string) {
    await page
        .getByRole('button', { name: 'Nueva categoría', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Nueva categoría' });
    await dialog.getByLabel('Nombre', { exact: true }).fill(name);
    await dialog.getByRole('button', { name: 'Guardar categoría' }).click();
    await expect(dialog).toBeHidden();
}

async function createItem(
    page: Page,
    name: string,
    category: string,
    kind: string,
) {
    await page
        .getByRole('button', { name: 'Nuevo insumo', exact: true })
        .click();
    const dialog = page.getByRole('dialog', { name: 'Nuevo insumo' });
    await dialog.getByLabel('Nombre', { exact: true }).fill(name);
    await dialog
        .getByLabel('Categoría', { exact: true })
        .selectOption({ label: category });
    await dialog.getByLabel('Clasificación').selectOption(kind);
    await dialog
        .getByLabel('Unidad de ingreso')
        .selectOption({ label: 'Kilogramo' });
    await dialog.getByRole('button', { name: 'Guardar insumo' }).click();
    await expect(dialog).toBeHidden();
}

test('administrator filters items by category, classification and name, and filters survive an edit', async ({
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

    await createCategory(page, 'Secos');
    await createCategory(page, 'Rellenos');
    await createItem(page, 'Harina', 'Secos', 'raw_material');
    await createItem(page, 'Caja docena', 'Rellenos', 'packaging');
    await createItem(page, 'Relleno Salteñas', 'Rellenos', 'semi_finished');

    const card = (name: string) => page.getByRole('article', { name });
    const chips = page.getByRole('group', {
        name: 'Filtrar insumos por categoría',
    });
    const search = page.getByRole('searchbox', {
        name: 'Buscar insumos por nombre',
    });
    await expect(chips.getByRole('button', { name: 'Todas' })).toHaveAttribute(
        'aria-pressed',
        'true',
    );
    await expect(page.getByRole('article')).toHaveCount(3);

    await chips.getByRole('button', { name: 'Rellenos', exact: true }).click();
    await expect(page).toHaveURL(/[?&]category=\d+/);
    await expect(card('Harina')).toBeHidden();
    await expect(page.getByRole('article')).toHaveCount(2);
    await expect(
        chips.getByRole('button', { name: 'Rellenos', exact: true }),
    ).toHaveAttribute('aria-pressed', 'true');

    await page.getByLabel('Clasificación').selectOption('packaging');
    await expect(page).toHaveURL(/[?&]kind=packaging/);
    await expect(page.getByRole('article')).toHaveCount(1);
    await expect(card('Caja docena')).toBeVisible();
    await page.getByLabel('Clasificación').selectOption('');
    await expect(page).not.toHaveURL(/kind=/);

    await search.fill('saltenas');
    await page.getByRole('button', { name: 'Buscar', exact: true }).click();
    await expect(page).toHaveURL(/[?&]search=saltenas/);
    await expect(page.getByRole('article')).toHaveCount(1);
    await expect(card('Relleno Salteñas')).toBeVisible();
    const filteredUrl = page.url();

    await card('Relleno Salteñas')
        .getByRole('button', { name: 'Editar insumo' })
        .click();
    const edit = page.getByRole('dialog', { name: 'Editar insumo' });
    await edit
        .getByLabel('Nombre', { exact: true })
        .fill('Relleno Salteñas criollas');
    await edit.getByRole('button', { name: 'Guardar insumo' }).click();
    await expect(edit).toBeHidden();
    await expect(page).toHaveURL(filteredUrl);
    await expect(search).toHaveValue('saltenas');
    await expect(page.getByRole('article')).toHaveCount(1);
    await expect(card('Relleno Salteñas criollas')).toBeVisible();

    await page.reload();
    await expect(search).toHaveValue('saltenas');
    await expect(
        chips.getByRole('button', { name: 'Rellenos', exact: true }),
    ).toHaveAttribute('aria-pressed', 'true');
    await expect(page.getByRole('article')).toHaveCount(1);

    await chips
        .getByRole('button', { name: 'Editar categoría Rellenos' })
        .click();
    const categoryEditor = page.getByRole('dialog', {
        name: 'Editar categoría',
    });
    await categoryEditor
        .getByLabel('Nombre', { exact: true })
        .fill('Rellenos y masas');
    await categoryEditor
        .getByRole('button', { name: 'Guardar categoría' })
        .click();
    await expect(categoryEditor).toBeHidden();
    await expect(page).toHaveURL(filteredUrl);
    await expect(
        chips.getByRole('button', { name: 'Rellenos y masas', exact: true }),
    ).toHaveAttribute('aria-pressed', 'true');

    await search.fill('nada que coincida');
    await page.getByRole('button', { name: 'Buscar', exact: true }).click();
    await expect(
        page.getByText('Ningún insumo coincide con los filtros.'),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Limpiar filtros' }).click();
    await expect(page).toHaveURL(/\/admin\/inventory$/);
    await expect(search).toHaveValue('');
    await expect(page.getByRole('article')).toHaveCount(3);
});
