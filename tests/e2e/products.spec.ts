import { expect, test } from '@playwright/test';

test('administrator creates, edits, and changes product availability', async ({
    page,
}, testInfo) => {
    const productName = `Playwright Product ${testInfo.project.name}`;
    const editedName = `${productName} Edited`;
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/admin$/);

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
    await createDialog.getByRole('checkbox', { name: 'Vegetariana' }).click();
    await createDialog.getByRole('button', { name: 'Create product' }).click();

    await expect(createDialog).toBeHidden();
    const createdCard = page.locator('[data-slot="card"]').filter({
        has: page.getByRole('heading', { name: productName, exact: true }),
    });
    await expect(createdCard).toBeVisible();
    await expect(
        createdCard.getByText('Vegetarian', { exact: true }),
    ).toBeVisible();

    await createdCard.getByRole('button', { name: 'Edit product' }).click();
    const editDialog = page.getByRole('dialog', { name: 'Edit product' });
    await editDialog.getByLabel('Name', { exact: true }).fill(editedName);
    await editDialog
        .getByRole('checkbox', { name: /Available for sale/ })
        .click();
    await editDialog.getByRole('button', { name: 'Save changes' }).click();

    await expect(editDialog).toBeHidden();
    const editedCard = page.locator('[data-slot="card"]').filter({
        has: page.getByRole('heading', { name: editedName, exact: true }),
    });
    await expect(editedCard).toBeVisible();
    await expect(
        editedCard.getByText('Unavailable', { exact: true }),
    ).toBeVisible();
});
