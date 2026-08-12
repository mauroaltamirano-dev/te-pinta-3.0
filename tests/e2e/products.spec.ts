import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const seededProducts = {
    Clásicas: ['Criolla Salada', 'Criolla Dulce', 'Jamón y Queso', 'Caprese'],
    Especiales: [
        'Salteña',
        'Humita Tucumana',
        'Hongos al puerro',
        'Pollo y portobellos',
        'Matambre y verduras asadas',
    ],
} as const;

async function logInAsAdministrator(page: Page) {
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

test('settings opens product management with the seeded catalog', async ({
    page,
}) => {
    await logInAsAdministrator(page);

    await page.goto('/admin/settings');
    await expect(page).toHaveURL(/\/admin\/settings\/products$/);
    await expect(
        page.getByRole('heading', { name: 'Products management' }),
    ).toBeVisible();

    for (const product of seededProducts.Clásicas) {
        await expect(
            page.getByRole('heading', { name: product, exact: true }),
        ).toBeVisible();
    }

    await page.getByRole('button', { name: 'Especiales' }).click();

    for (const product of seededProducts.Especiales) {
        await expect(
            page.getByRole('heading', { name: product, exact: true }),
        ).toBeVisible();
    }
});

test('administrator creates, edits, and changes product availability', async ({
    page,
}, testInfo) => {
    const productName = `Playwright Product ${testInfo.project.name}`;
    const editedName = `${productName} Edited`;
    await logInAsAdministrator(page);

    await page.goto('/admin/settings/products');
    await expect(
        page.getByRole('heading', { name: 'Products management' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Criolla Salada' }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Add product' }).click();
    const createDialog = page.getByRole('dialog', { name: 'Add product' });
    await createDialog.getByLabel('Name', { exact: true }).fill(productName);
    await createDialog
        .getByLabel('Short description')
        .fill('Created through the administrative flow.');
    await createDialog.getByLabel('Unit', { exact: true }).fill('1800');
    await createDialog.getByLabel('Half dozen', { exact: true }).fill('10000');
    await createDialog.getByLabel('Dozen', { exact: true }).fill('18000');
    await createDialog.getByLabel('Order within category').fill('99');
    const vegetarianTag = createDialog.getByRole('checkbox', {
        name: 'Vegetariana',
    });
    await vegetarianTag.focus();
    await vegetarianTag.press('Space');
    const createProduct = createDialog.getByRole('button', {
        name: 'Create product',
    });
    await createProduct.focus();
    await createProduct.press('Enter');

    await expect(createDialog).toBeHidden();
    const createdCard = page.locator('[data-slot="card"]').filter({
        has: page.getByRole('heading', { name: productName, exact: true }),
    });
    await expect(createdCard).toBeVisible();
    await expect(
        createdCard.getByText('Vegetarian', { exact: true }),
    ).toBeVisible();

    const editProduct = createdCard.getByRole('button', {
        name: 'Edit product',
    });
    await editProduct.focus();
    await editProduct.press('Enter');
    const editDialog = page.getByRole('dialog', { name: 'Edit product' });
    await editDialog.getByLabel('Name', { exact: true }).fill(editedName);
    const availability = editDialog.getByRole('checkbox', {
        name: /Available for sale/,
    });
    await availability.focus();
    await availability.press('Space');
    const saveProduct = editDialog.getByRole('button', {
        name: 'Save changes',
    });
    await saveProduct.focus();
    await saveProduct.press('Enter');

    await expect(editDialog).toBeHidden();
    const editedCard = page.locator('[data-slot="card"]').filter({
        has: page.getByRole('heading', { name: editedName, exact: true }),
    });
    await expect(editedCard).toBeVisible();
    await expect(
        editedCard.getByText('Unavailable', { exact: true }),
    ).toBeVisible();
});
