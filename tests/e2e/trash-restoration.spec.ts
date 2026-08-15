import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

async function logIn(page: Page) {
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

async function createOrder(page: Page, customerName: string) {
    await page.goto('/admin/orders/create');
    await page
        .getByRole('button', { name: 'Agregar una docena de Salteña' })
        .click();
    await expect(page.getByText('Cantidad: 12')).toBeVisible();
    await page.getByLabel('Nombre').fill(customerName);
    await page.getByRole('button', { name: 'Crear pedido' }).click();
    await expect(page).toHaveURL(/\/admin\/orders\/TP-\d+$/);

    return page.url().split('/').at(-1) as string;
}

test.describe.configure({ mode: 'serial' });
test.beforeEach(({ page }, testInfo) => {
    void page;
    test.skip(
        testInfo.project.name !== 'chromium',
        'Trash restoration is covered once at the desktop-priority viewport.',
    );
});

test('an administrator can restore deleted orders and customers from their trash pages', async ({
    page,
}) => {
    test.setTimeout(60_000);
    await logIn(page);
    const customerName = 'Trash restoration customer';
    const orderId = await createOrder(page, customerName);
    expect(orderId).toMatch(/^TP-\d+$/);

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Eliminar pedido' }).click();
    await expect(page).toHaveURL(/\/admin\/orders$/);

    await page.getByRole('link', { name: 'Papelera' }).click();
    await expect(page).toHaveURL(/\/admin\/orders\/trash$/);
    const restoreOrder = page.getByRole('button', {
        name: `Restaurar ${orderId}`,
    });
    await expect(restoreOrder).toBeVisible();
    await restoreOrder.click();
    await expect(restoreOrder).toHaveCount(0);
    await page.goto(`/admin/orders/${orderId}`);
    await expect(page.getByRole('heading', { name: orderId })).toBeVisible();

    await page.goto('/admin/customers');
    const customerLink = page.getByRole('link', {
        name: customerName,
        exact: true,
    });
    await customerLink.click();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Remove' }).click();
    await expect(page).toHaveURL(/\/admin\/customers$/);

    await page.getByRole('link', { name: 'Trash' }).click();
    await expect(page).toHaveURL(/\/admin\/customers\/trash$/);
    const restoreCustomer = page.getByRole('button', {
        name: `Restore ${customerName}`,
    });
    await expect(restoreCustomer).toBeVisible();
    await restoreCustomer.click();
    await expect(restoreCustomer).toHaveCount(0);
    await page.getByRole('link', { name: 'Back to customers' }).click();
    await expect(
        page.getByRole('link', { name: customerName, exact: true }),
    ).toBeVisible();
});

test('customer restoration shows an actionable error when the phone belongs to an active customer', async ({
    page,
}) => {
    await logIn(page);
    await page.goto('/admin/customers');

    await page.getByRole('button', { name: 'Create customer' }).click();
    let dialog = page.getByRole('dialog');
    await dialog.getByLabel('Name').fill('Deleted phone owner');
    await dialog.getByLabel('Phone').fill('+54 381 555 8801');
    await dialog.getByRole('button', { name: 'Create customer' }).click();
    await expect(dialog).toHaveCount(0);

    page.once('dialog', (confirmation) => confirmation.accept());
    await page.getByRole('button', { name: 'Remove' }).click();
    await expect(page).toHaveURL(/\/admin\/customers$/);

    await page.getByRole('button', { name: 'Create customer' }).click();
    dialog = page.getByRole('dialog');
    await dialog.getByLabel('Name').fill('Active phone owner');
    await dialog.getByLabel('Phone').fill('+54 (381) 555-8801');
    await dialog.getByRole('button', { name: 'Create customer' }).click();
    await expect(dialog).toHaveCount(0);

    await page.goto('/admin/customers/trash');
    const restore = page.getByRole('button', {
        name: 'Restore Deleted phone owner',
    });
    await restore.click();

    await expect(page.getByRole('alert')).toHaveText(
        'No se puede restaurar el cliente porque su teléfono está asignado a otro cliente activo. Modificá ese cliente y volvé a intentarlo.',
    );
    await expect(restore).toBeVisible();
});
