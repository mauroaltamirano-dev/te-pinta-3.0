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

async function captureCopiedOrderDetail(page: Page) {
    await page.evaluate(() => {
        const target = window as Window & { copiedOrderDetail?: string };
        target.copiedOrderDetail = '';
        Object.defineProperty(navigator, 'clipboard', {
            configurable: true,
            value: {
                writeText: async (value: string) => {
                    target.copiedOrderDetail = value;
                },
            },
        });
    });
}

async function copiedOrderDetail(page: Page) {
    return page.evaluate(
        () =>
            (window as Window & { copiedOrderDetail?: string })
                .copiedOrderDetail ?? '',
    );
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

    const orderId = page.url().split('/').at(-1);
    expect(orderId).toMatch(/^TP-\d+$/);

    return orderId as string;
}

function summaryValue(page: Page, label: string) {
    return page
        .getByText(label, { exact: true })
        .locator('..')
        .locator('span')
        .nth(1);
}

async function addOneSaltenaAndWaitForPreview(page: Page) {
    const preview = page.waitForResponse((response) => {
        const request = response.request();

        return (
            request.method() === 'POST' &&
            new URL(response.url()).pathname ===
                '/admin/orders/price-preview' &&
            response.ok()
        );
    });

    await page
        .getByRole('button', { name: 'Agregar una unidad de Salteña' })
        .click();
    await preview;
    await expect(page.getByText('Cantidad: 13')).toBeVisible();
}

test.describe.configure({ mode: 'serial' });

test('inicio móvil muestra navegación práctica y variedades en dos columnas', async ({
    page,
}, testInfo) => {
    test.skip(
        testInfo.project.name !== 'mobile-chromium',
        'La adaptación táctil se valida en el viewport móvil.',
    );
    await logIn(page);
    await page.goto('/admin');

    const navigation = page.getByRole('navigation', {
        name: 'Navegación principal',
    });
    await expect(navigation).toBeVisible();
    await expect(
        navigation.getByRole('link', { name: 'Inicio' }),
    ).toBeVisible();
    await expect(
        navigation.getByRole('link', { name: 'Pedidos' }),
    ).toBeVisible();
    await expect(
        navigation.getByRole('link', { name: 'Clientes' }),
    ).toBeVisible();
    await expect(
        navigation.getByRole('button', { name: 'Abrir más opciones' }),
    ).toBeVisible();

    const cards = page.locator('article').filter({
        has: page.getByRole('button', { name: /Agregar 1 unidad de/ }),
    });
    await expect(cards).toHaveCount(9);
    const first = await cards.nth(0).boundingBox();
    const second = await cards.nth(1).boundingBox();
    expect(Math.abs((first?.y ?? 0) - (second?.y ?? 0))).toBeLessThan(2);
    expect(second?.x ?? 0).toBeGreaterThan(first?.x ?? 0);

    await page.screenshot({
        path: 'storage/app/admin-quick-order-mobile-grid.png',
        fullPage: true,
    });
});

test('administrador crea un pedido cocinado con desglose explícito', async ({
    page,
}) => {
    await logIn(page);
    await page.goto('/admin/orders/create');

    const cookedButton = page.getByRole('button', { name: 'Cocinado' });
    await cookedButton.click();
    await expect(cookedButton).toHaveAttribute('aria-pressed', 'true');
    await page
        .getByRole('button', { name: 'Agregar una docena de Salteña' })
        .click();
    await expect(page.getByText('Cantidad: 12')).toBeVisible();
    await page.getByLabel('Nombre').fill('Cliente Playwright');

    await expect(page.getByText('Resumen del pedido')).toBeVisible();
    await expect(page.getByText('Empanadas base')).toBeVisible();
    await expect(page.getByText('Cocción')).toBeVisible();
    await page.getByRole('button', { name: 'Crear pedido' }).click();

    await expect(page).toHaveURL(/\/admin\/orders\/TP-/);
    await expect(page.getByRole('heading', { name: /TP-/ })).toBeVisible();
    await expect(page.getByText('Cocción')).toBeVisible();
});

test('detalle compartido prioriza el ticket y copia el resumen estructurado', async ({
    page,
}, testInfo) => {
    test.skip(
        testInfo.project.name !== 'chromium',
        'Desktop validates the shared ticket identity; mobile has its own responsive capture.',
    );
    await logIn(page);
    await page.goto('/admin/orders');
    const orderButton = page.getByRole('button', { name: /TP-/ }).first();
    const orderId = await orderButton.textContent();
    await orderButton.click();

    const ticket = page.getByTestId('order-ticket-dialog');
    await expect(ticket).toContainText(orderId ?? '');
    await captureCopiedOrderDetail(page);
    await ticket.getByRole('button', { name: 'Copiar detalle' }).click();
    await expect(
        ticket.getByRole('button', { name: 'Detalle copiado' }),
    ).toBeVisible();
    const copied = await copiedOrderDetail(page);
    expect(copied).toContain(`🧾 *PEDIDO ${orderId}*`);
    expect(copied).toContain('👤 *Cliente:* Cliente Playwright');
    expect(copied).toContain('🔥 *Condición:* Cocinadas');
    expect(copied).toContain('• 1 × Docena de Salteña');
    expect(copied).toContain('💰 *TOTAL:');
    expect(copied).not.toContain('5555-0101');

    await page.waitForTimeout(600);
    await page.screenshot({
        path: 'storage/app/orders-detail-ticket-desktop.png',
        fullPage: true,
    });

    await expect(ticket.getByRole('link', { name: 'Editar' })).toHaveAttribute(
        'href',
        `/admin/orders/${orderId}/edit`,
    );
});

test('lista abre un ticket y permite actualizar estado y pago rápidamente', async ({
    page,
}, testInfo) => {
    if (testInfo.project.name === 'chromium') {
        await page.setViewportSize({ width: 1659, height: 916 });
    }

    await logIn(page);
    await page.goto('/admin/orders');
    await expect(
        page.getByRole('heading', { name: 'Pedidos', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Más pedidos' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Distribución de pedidos' }),
    ).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Entrega' })).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Condición' }),
    ).toBeVisible();

    if (testInfo.project.name === 'chromium') {
        const grid = await page
            .getByTestId('orders-dashboard-grid')
            .boundingBox();
        const primaryColumn = await page
            .getByTestId('orders-primary-column')
            .boundingBox();
        const insightsColumn = await page
            .getByLabel('Resumen de pedidos')
            .boundingBox();
        const report = await page.getByTestId('orders-report').boundingBox();
        const insightsProducts = await page
            .getByTestId('orders-insights-products')
            .boundingBox();
        const insightsDelivery = await page
            .getByTestId('orders-insights-delivery')
            .boundingBox();
        const summaryCards = page.getByTestId('order-summary-card');

        const availableWidth = (page.viewportSize()?.width ?? 0) - 104 - 48;
        expect(grid?.width).toBe(availableWidth);
        expect(
            (insightsColumn?.x ?? 0) -
                (primaryColumn?.x ?? 0) -
                (primaryColumn?.width ?? 0),
        ).toBe(24);
        expect(
            (primaryColumn?.width ?? 0) / (insightsColumn?.width ?? 1),
        ).toBeCloseTo(646 / 372, 1);
        await expect(summaryCards).toHaveCount(3);
        expect(
            (await summaryCards.first().boundingBox())?.width,
        ).toBeGreaterThanOrEqual(199);
        expect((await summaryCards.first().boundingBox())?.height).toBe(143);
        expect(grid?.height).toBe((page.viewportSize()?.height ?? 0) - 48);
        expect(primaryColumn?.height).toBe(grid?.height);
        expect(insightsColumn?.height).toBe(grid?.height);
        expect(
            (insightsProducts?.height ?? 0) / (insightsDelivery?.height ?? 1),
        ).toBeCloseTo(429 / 333, 1);
        expect(
            (insightsDelivery?.y ?? 0) -
                (insightsProducts?.y ?? 0) -
                (insightsProducts?.height ?? 0),
        ).toBe(24);
        expect((report?.height ?? 0) > 0).toBe(true);
        await expect(page.getByTestId('orders-report-scroll')).toHaveCSS(
            'overflow-y',
            'auto',
        );
        await expect(page.getByTestId('orders-report-head')).toHaveCSS(
            'position',
            'sticky',
        );
        expect(
            await page.evaluate(() => document.documentElement.scrollHeight),
        ).toBe(page.viewportSize()?.height);

        await page.screenshot({
            path: 'storage/app/orders-redesign.png',
            fullPage: true,
        });
    }

    const orderButton = page.getByRole('button', { name: /TP-/ }).first();
    const orderId = await orderButton.textContent();
    await orderButton.click();

    const ticket = page.getByTestId('order-ticket-dialog');
    await expect(ticket).toBeVisible();
    await expect(ticket).toContainText(orderId ?? '');
    await expect(ticket.getByText('Cliente Playwright')).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`order=${orderId}`));

    if (testInfo.project.name === 'chromium') {
        await page.waitForTimeout(600);
        await page.screenshot({
            path: 'storage/app/orders-ticket-dialog.png',
            fullPage: true,
        });
    }

    await ticket.getByRole('button', { name: 'Cerrar' }).click();
    await page.getByLabel(`Estado de ${orderId}`).click();
    await page.getByRole('option', { name: 'Preparado' }).click();
    await expect(page.getByLabel(`Estado de ${orderId}`)).toHaveText(
        /Preparado/,
    );

    await page
        .getByRole('switch', { name: `Marcar como pagado ${orderId}` })
        .click();
    await page.getByLabel(`Medio de pago de ${orderId}`).click();
    await page.getByRole('option', { name: 'Efectivo', exact: true }).click();
    await page.getByRole('button', { name: 'Confirmar pago' }).click();
    await expect(
        page.getByRole('switch', { name: `Marcar como no pagado ${orderId}` }),
    ).toHaveAttribute('aria-checked', 'true');

    await expect(ticket).toBeHidden();
    await expect(page).toHaveURL(/\/admin\/orders(?:\?.*)?$/);

    await page.goBack();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.goForward();
    await expect(page.getByRole('dialog')).toBeHidden();

    await page.goto(`/admin/orders/${orderId}`);
    await expect(
        page.getByRole('heading', { name: orderId ?? /TP-/ }),
    ).toBeVisible();
    await expect(page.getByText('Resumen del pedido')).toBeVisible();
    await expect(page.getByTestId('order-ticket-header')).toContainText(
        orderId ?? '',
    );
    await expect(
        page.getByRole('button', { name: 'Copiar detalle' }),
    ).toBeVisible();
});

test('ticket mantiene el resumen usable en viewport móvil', async ({
    page,
}, testInfo) => {
    await logIn(page);
    await page.goto('/admin/orders');
    await page.getByRole('button', { name: /TP-/ }).first().click();

    const ticket = page.getByTestId('order-ticket-dialog');
    await expect(ticket).toBeVisible();
    const ticketWidth = (await ticket.boundingBox())?.width ?? 0;
    const viewportWidth = page.viewportSize()?.width ?? 0;
    expect(ticketWidth).toBeLessThanOrEqual(viewportWidth - 16);

    await expect(ticket.getByText('Comprobante de pedido')).toBeVisible();
    await expect(ticket.getByText(/\d{2}\/\d{2}\/\d{4} ·/)).toBeVisible();
    await expect(
        ticket.getByRole('button', { name: 'Copiar detalle' }),
    ).toBeVisible();
    await expect(ticket.getByRole('link', { name: 'Editar' })).toBeVisible();

    if (testInfo.project.name === 'mobile-chromium') {
        await page.waitForTimeout(600);
        await page.screenshot({
            path: 'storage/app/orders-ticket-mobile.png',
            fullPage: true,
        });
    }
});

test.describe('edición administrativa segura', () => {
    test.beforeEach(async ({ page }, testInfo) => {
        test.skip(
            testInfo.project.name !== 'chromium',
            'The write journey uses the isolated desktop Playwright database serially.',
        );
        await logIn(page);
    });

    test('edita desde el ticket, repricing mediante backend y conserva el Order ID', async ({
        page,
    }) => {
        const orderId = await createOrder(page, 'Edición activa Playwright');
        const originalDetailTotal = await page
            .getByRole('heading', { name: 'Resumen del pedido' })
            .locator('..')
            .getByText(/\$/)
            .last()
            .textContent();

        await page.goto(`/admin/orders?search=${orderId}`);
        await page.getByRole('button', { name: orderId, exact: true }).click();
        const ticket = page.getByTestId('order-ticket-dialog');
        await expect(
            ticket.getByRole('link', { name: 'Editar' }),
        ).toHaveAttribute('href', `/admin/orders/${orderId}/edit`);
        await ticket.getByRole('link', { name: 'Editar' }).click();
        await expect(page).toHaveURL(`/admin/orders/${orderId}/edit`);
        await expect(page.getByText(`Editando ${orderId}`)).toBeVisible();

        const originalTotal = await summaryValue(
            page,
            'Total anterior',
        ).textContent();
        expect(originalTotal).toBe(originalDetailTotal);

        await addOneSaltenaAndWaitForPreview(page);

        const updatedTotal = await summaryValue(
            page,
            'Total actualizado',
        ).textContent();
        expect(updatedTotal).not.toBe(originalTotal);
        await expect(summaryValue(page, 'Diferencia total')).toContainText('+');

        const update = page.waitForResponse((response) => {
            const request = response.request();

            return (
                request.method() === 'PUT' &&
                new URL(response.url()).pathname === `/admin/orders/${orderId}`
            );
        });
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        expect((await update).status()).toBe(303);

        await expect(page).toHaveURL(`/admin/orders/${orderId}`);
        await expect(
            page.getByRole('heading', { name: orderId }),
        ).toBeVisible();
        await expect(
            page
                .getByRole('heading', { name: 'Resumen del pedido' })
                .locator('..'),
        ).toContainText(updatedTotal ?? '');

        await page.getByRole('link', { name: 'Editar pedido' }).click();
        await expect(page.getByText('Cantidad: 13')).toBeVisible();
    });

    test('cancelar edición no persiste el draft modificado', async ({
        page,
    }) => {
        const orderId = await createOrder(page, 'Cancelar edición Playwright');
        const originalTotal = await page
            .getByRole('heading', { name: 'Resumen del pedido' })
            .locator('..')
            .getByText(/\$/)
            .last()
            .textContent();

        await page.getByRole('link', { name: 'Editar pedido' }).click();
        await addOneSaltenaAndWaitForPreview(page);
        await expect(summaryValue(page, 'Total actualizado')).not.toHaveText(
            originalTotal ?? '',
        );

        await page.getByRole('button', { name: 'Cancelar edición' }).click();
        await expect(page).toHaveURL(`/admin/orders/${orderId}`);
        await page.reload();
        await expect(
            page
                .getByRole('heading', { name: 'Resumen del pedido' })
                .locator('..'),
        ).toContainText(originalTotal ?? '');

        await page.getByRole('link', { name: 'Editar pedido' }).click();
        await expect(page.getByText('Cantidad: 12')).toBeVisible();
        await expect(page.getByText('Cantidad: 13')).toHaveCount(0);
    });

    test('pedido entregado exige habilitación y motivo, y conserva su estado al editar', async ({
        page,
    }) => {
        const orderId = await createOrder(page, 'Edición cerrada Playwright');

        await page.getByLabel('Estado del pedido').click();
        const statusUpdate = page.waitForResponse((response) => {
            const request = response.request();

            return (
                request.method() === 'PATCH' &&
                new URL(response.url()).pathname ===
                    `/admin/orders/${orderId}/status`
            );
        });
        await page.getByRole('option', { name: 'Entregado' }).click();
        expect((await statusUpdate).status()).toBe(303);

        await expect(
            page.getByText('Pedido cerrado — edición bloqueada'),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Editar pedido' }),
        ).toHaveCount(0);

        await page.goto(`/admin/orders?search=${orderId}`);
        await page.getByRole('button', { name: orderId, exact: true }).click();
        const ticket = page.getByTestId('order-ticket-dialog');
        await ticket.getByRole('button', { name: 'Editar' }).click();

        const confirmation = page
            .getByRole('dialog')
            .filter({ hasText: `Habilitar edición de ${orderId}` });
        await expect(
            confirmation.getByText('Vas a modificar un pedido cerrado.'),
        ).toBeVisible();
        const confirm = confirmation.getByRole('button', {
            name: 'Confirmar y editar',
        });
        await expect(confirm).toBeDisabled();
        await confirmation.getByLabel('Motivo breve').fill('x');
        await expect(confirm).toBeDisabled();
        await confirmation
            .getByLabel('Motivo breve')
            .fill('Corregir una nota administrativa');
        await expect(confirm).toBeEnabled();
        await confirm.click();

        await expect(page).toHaveURL(`/admin/orders/${orderId}/edit`);
        await expect(page.getByText(`Editando ${orderId}`)).toBeVisible();
        await page
            .getByLabel('Notas del pedido')
            .fill('Nota corregida en pedido entregado');
        await page.getByRole('button', { name: 'Guardar cambios' }).click();

        await expect(page).toHaveURL(`/admin/orders/${orderId}`);
        await expect(
            page.getByText('Entregado', { exact: true }).first(),
        ).toBeVisible();
        await expect(
            page.getByText('Nota corregida en pedido entregado'),
        ).toBeVisible();
        await expect(
            page.getByText('Pedido cerrado — edición bloqueada'),
        ).toBeVisible();
    });
});
