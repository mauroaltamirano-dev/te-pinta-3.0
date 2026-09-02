import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

async function logIn(page: Page) {
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
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
        'La restauración desde papelera se cubre una vez en el viewport prioritario de escritorio.',
    );
});

test('un administrador restaura pedidos y clientes desde sus papeleras', async ({
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
    await page
        .getByLabel('Buscar clientes por nombre o teléfono')
        .fill(customerName);
    await page.getByRole('button', { name: 'Buscar', exact: true }).click();
    const customerLink = page.getByRole('link', {
        name: customerName,
        exact: true,
    });
    await customerLink.click();

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Enviar a papelera' }).click();
    await expect(page).toHaveURL(/\/admin\/customers$/);

    await page.getByRole('link', { name: 'Papelera' }).click();
    await expect(page).toHaveURL(/\/admin\/customers\/trash$/);
    const restoreCustomer = page.getByRole('button', {
        name: `Restaurar ${customerName}`,
    });
    await expect(restoreCustomer).toBeVisible();
    await restoreCustomer.click();
    await expect(restoreCustomer).toHaveCount(0);
    await page.getByRole('link', { name: 'Volver a clientes' }).click();
    await page
        .getByLabel('Buscar clientes por nombre o teléfono')
        .fill(customerName);
    await page.getByRole('button', { name: 'Buscar', exact: true }).click();
    await expect(
        page.getByRole('link', { name: customerName, exact: true }),
    ).toBeVisible();
});

test('restaurar un cliente muestra un error accionable si el teléfono pertenece a otro activo', async ({
    page,
}) => {
    await logIn(page);
    await page.goto('/admin/customers');

    await page.getByRole('button', { name: 'Crear cliente' }).click();
    let dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nombre').fill('Deleted phone owner');
    await dialog.getByLabel('Teléfono').fill('+54 381 555 8801');
    await dialog.getByRole('button', { name: 'Crear cliente' }).click();
    await expect(dialog).toHaveCount(0);

    page.once('dialog', (confirmation) => confirmation.accept());
    await page.getByRole('button', { name: 'Enviar a papelera' }).click();
    await expect(page).toHaveURL(/\/admin\/customers$/);

    await page.getByRole('button', { name: 'Crear cliente' }).click();
    dialog = page.getByRole('dialog');
    await dialog.getByLabel('Nombre').fill('Active phone owner');
    await dialog.getByLabel('Teléfono').fill('+54 (381) 555-8801');
    await dialog.getByRole('button', { name: 'Crear cliente' }).click();
    await expect(dialog).toHaveCount(0);

    await page.goto('/admin/customers/trash');
    const restore = page.getByRole('button', {
        name: 'Restaurar Deleted phone owner',
    });
    await restore.click();

    await expect(page.getByRole('alert')).toHaveText(
        'No se puede restaurar el cliente porque su teléfono está asignado a otro cliente activo. Modificá ese cliente y volvé a intentarlo.',
    );
    await expect(restore).toBeVisible();
});
