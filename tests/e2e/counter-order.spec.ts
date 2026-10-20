import { expect, test } from './support/test';

test.beforeEach(({ page }, testInfo) => {
    void page;
    test.skip(
        testInfo.project.name !== 'chromium',
        'El pedido de mostrador se carga en la PC del local.',
    );
});

test('un pedido de mostrador se confirma sin cliente y queda en cola de impresión', async ({
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

    await page
        .getByRole('button', { name: 'Agregar 6 unidades de Salteña' })
        .click();
    const draft = page.locator('aside').filter({ hasText: 'Pedido actual' });
    await draft.getByRole('button', { name: 'Continuar' }).click();
    const continuation = page.getByRole('dialog');

    // A customer chosen before switching to Mostrador is not attached to the counter order.
    await continuation.getByLabel('Buscar cliente').fill('habitual');
    await continuation
        .getByRole('button', { name: /Cliente habitual/ })
        .click();
    await continuation.getByRole('button', { name: 'Mostrador' }).click();
    await expect(continuation.getByLabel('Buscar cliente')).toHaveCount(0);
    await expect(
        continuation.getByText('Consumo en el local · sin cliente'),
    ).toBeVisible();
    await expect(continuation.getByLabel('Franja')).toHaveCount(0);
    await expect(continuation.getByLabel('Dirección de entrega')).toHaveCount(
        0,
    );

    await continuation
        .getByRole('button', { name: 'Confirmar pedido' })
        .click();
    const orderHeading = continuation.getByRole('heading', { name: /TP-\d+/ });
    await expect(orderHeading).toBeVisible();
    const orderId = (await orderHeading.textContent())?.trim();
    await page.goto(`/admin/orders/${orderId}`);
    await expect(page.getByText('Salón').first()).toBeVisible();
    await expect(page.getByText('Cliente habitual')).toHaveCount(0);

    await page.goto('/admin/print-station');
    await expect(page.getByTestId('pending-count')).toHaveText('1');
});
