import type { Page, TestInfo } from '@playwright/test';
import { expect, test } from './support/test';

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

async function configureOrderPaymentWallet(page: Page, walletName: string) {
    await page.goto('/admin/management?section=wallets');
    await page
        .getByRole('button', { name: 'Nueva billetera', exact: true })
        .click();
    const dialog = page.getByRole('dialog');

    await dialog.getByLabel('Nombre').fill(walletName);
    await dialog.getByLabel('Recibir cobros de pedidos').check();
    await dialog
        .getByRole('button', { name: 'Crear billetera', exact: true })
        .click();
    await expect(dialog).toHaveCount(0);
    await page.waitForLoadState('networkidle');
}

async function createMixedFlavorOrder(page: Page, customerName: string) {
    await page.goto('/admin/orders/create');
    await page
        .getByRole('button', { name: 'Agregar media docena de Salteña' })
        .click();
    await page
        .getByRole('button', {
            name: 'Agregar media docena de Humita Tucumana',
        })
        .click();
    await expect(page.getByText('Cantidad: 6')).toHaveCount(2);
    await page.getByLabel('Nombre').fill(customerName);
    await page.getByRole('button', { name: 'Crear pedido' }).click();
    await expect(page).toHaveURL(/\/admin\/orders\/TP-\d+$/);

    const orderId = page.url().split('/').at(-1);
    expect(orderId).toMatch(/^TP-\d+$/);

    return orderId as string;
}

async function createDefaultCookedOrder(page: Page, customerName: string) {
    await page.goto('/admin/orders/create');
    await expect(
        page.getByRole('checkbox', { name: 'Entregar crudas' }),
    ).not.toBeChecked();
    await page
        .getByRole('button', { name: 'Agregar una docena de Salteña' })
        .click();
    await expect(page.getByText('Cantidad: 12')).toBeVisible();
    await page.getByLabel('Nombre').fill(customerName);
    await expect(page.getByText('Resumen del pedido')).toBeVisible();
    await expect(page.getByText('Productos cocinados')).toBeVisible();
    await expect(
        page.getByText('Descuento por preparación en crudo'),
    ).toHaveCount(0);
    await page.getByRole('button', { name: 'Crear pedido' }).click();
    await expect(page).toHaveURL(/\/admin\/orders\/TP-\d+$/);

    const orderId = page.url().split('/').at(-1);
    expect(orderId).toMatch(/^TP-\d+$/);

    return orderId as string;
}

function scenarioCustomerName(testInfo: TestInfo, scenario: string) {
    return `${scenario} ${testInfo.project.name}`;
}

function orderCardForId(page: Page, orderId: string) {
    return page.getByTestId('order-card').filter({
        has: page.getByRole('button', { name: orderId, exact: true }),
    });
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
        navigation.getByRole('link', { name: 'Vender' }),
    ).toBeVisible();
    await expect(
        navigation.getByRole('link', { name: 'Pedidos' }),
    ).toBeVisible();
    await expect(navigation.getByRole('link', { name: 'Panel' })).toBeVisible();
    await expect(
        navigation.getByRole('button', { name: 'Abrir más opciones' }),
    ).toBeVisible();

    const cards = page.locator('article').filter({
        has: page.getByRole('button', { name: /Agregar 1 unidad de/ }),
    });
    await expect(cards).toHaveCount(10);
    const first = await cards.nth(0).boundingBox();
    const second = await cards.nth(1).boundingBox();
    expect(Math.abs((first?.y ?? 0) - (second?.y ?? 0))).toBeLessThan(2);
    expect(second?.x ?? 0).toBeGreaterThan(first?.x ?? 0);

    await page.screenshot({
        path: 'storage/app/admin-quick-order-mobile-grid.png',
        fullPage: true,
    });
});

test('admin order selector hides tier quick-add controls for a unit product', async ({
    page,
}) => {
    await logIn(page);
    await page.goto('/admin/orders/create');

    const product = page.locator('article').filter({
        hasText: 'Bebida individual',
    });

    await expect(product).toBeVisible();
    await expect(
        product.getByRole('button', {
            name: 'Agregar una unidad de Bebida individual',
        }),
    ).toBeVisible();
    await expect(
        product.getByRole('button', {
            name: 'Agregar media docena de Bebida individual',
        }),
    ).toHaveCount(0);
    await expect(
        product.getByRole('button', {
            name: 'Agregar una docena de Bebida individual',
        }),
    ).toHaveCount(0);
});

test('administrador crea un pedido cocinado con desglose explícito', async ({
    page,
}, testInfo) => {
    await logIn(page);
    const customerName = scenarioCustomerName(
        testInfo,
        'Cliente cocinado Playwright',
    );

    const orderId = await createDefaultCookedOrder(page, customerName);

    await expect(page.getByRole('heading', { name: orderId })).toBeVisible();
    await expect(page.getByText(customerName, { exact: true })).toBeVisible();
    await expect(page.getByText('Productos cocinados')).toBeVisible();
    await expect(page.getByText(/recargo por cocción/i)).toHaveCount(0);
});

test('grilla y lista mantienen completa la primera fila con scroll de resultados', async ({
    page,
}, testInfo) => {
    if (testInfo.project.name === 'chromium') {
        await page.setViewportSize({ width: 1659, height: 916 });
    }

    await logIn(page);
    const fixturePrefix = scenarioCustomerName(testInfo, 'Cliente de grilla');
    await createOrder(page, `${fixturePrefix} uno`);
    await createOrder(page, `${fixturePrefix} dos`);
    await createOrder(page, `${fixturePrefix} tres`);
    await createOrder(page, `${fixturePrefix} cuatro`);
    const orderId = await createMixedFlavorOrder(
        page,
        `${fixturePrefix} con variedades`,
    );
    await page.goto('/admin/orders');

    const cards = page.getByTestId('order-card');
    const card = orderCardForId(page, orderId);
    await expect(card).toBeVisible();
    await expect(card.getByText('6 × Salteña', { exact: true })).toBeVisible();
    await expect(
        card.getByText('6 × Humita Tucumana', { exact: true }),
    ).toBeVisible();
    // Fixed dozen pricing: mixed flavors are separate lines, never a combined dozen with varieties.
    await expect(card.getByText('Variedades', { exact: true })).toHaveCount(0);
    await expect(card.locator('img')).toHaveCount(0);
    await expect(page.getByTestId('orders-card-grid')).toHaveAttribute(
        'data-view',
        'grid',
    );
    await expect(page.getByTestId('orders-report-scroll')).toHaveCount(0);
    await expect(
        card.getByRole('button', { name: /Ver comprobante/ }),
    ).toBeVisible();
    await expect(card.getByRole('switch')).toBeVisible();

    const first = await cards.nth(0).boundingBox();
    const second = await cards.nth(1).boundingBox();

    if (testInfo.project.name === 'mobile-chromium') {
        expect(Math.abs((first?.x ?? 0) - (second?.x ?? 0))).toBeLessThan(2);
        expect(second?.y ?? 0).toBeGreaterThan(
            (first?.y ?? 0) + (first?.height ?? 0),
        );
        expect(
            await page.evaluate(() => document.documentElement.scrollHeight),
        ).toBeGreaterThan(page.viewportSize()?.height ?? 0);
    } else {
        expect(Math.abs((first?.y ?? 0) - (second?.y ?? 0))).toBeLessThan(2);
        expect(second?.x ?? 0).toBeGreaterThan(first?.x ?? 0);

        const results = page.getByTestId('orders-results-scroll');
        const resultsBox = await results.boundingBox();
        await expect(results).toHaveCSS('overflow-y', 'auto');
        await expect(results).toHaveCSS('overflow-x', 'hidden');
        expect(
            await results.evaluate(
                (element) => element.scrollHeight > element.clientHeight,
            ),
        ).toBe(true);

        // The labelled sidebar decides how many cards fit, so measure the real first row.
        const firstRow = [];

        for (let index = 0; index < (await cards.count()); index++) {
            const box = await cards.nth(index).boundingBox();

            if (Math.abs((box?.y ?? 0) - (first?.y ?? 0)) < 2) {
                firstRow.push(cards.nth(index));
            }
        }

        expect(firstRow.length).toBeGreaterThanOrEqual(2);

        for (const firstRowCard of firstRow) {
            const cardBox = await firstRowCard.boundingBox();
            expect(
                (cardBox?.y ?? 0) + (cardBox?.height ?? 0),
            ).toBeLessThanOrEqual(
                (resultsBox?.y ?? 0) + (resultsBox?.height ?? 0) + 1,
            );
            await expect(
                firstRowCard.getByRole('button', {
                    name: 'Ver comprobante',
                }),
            ).toBeVisible();
            await expect(firstRowCard.getByRole('switch')).toBeVisible();
        }

        expect(
            await page.evaluate(() => document.documentElement.scrollHeight),
        ).toBe(page.viewportSize()?.height);
    }

    await page.screenshot({
        path:
            testInfo.project.name === 'mobile-chromium'
                ? 'storage/app/orders-workspace-mobile-grid.png'
                : 'storage/app/orders-workspace-desktop-grid.png',
        fullPage: true,
    });

    await page.getByLabel('Vista en lista').click();
    await expect(page).toHaveURL(/view=list/);
    await expect(page.getByTestId('orders-card-grid')).toHaveAttribute(
        'data-view',
        'list',
    );
    await expect(card.getByText('6 × Salteña', { exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByLabel('Vista en lista')).toHaveAttribute(
        'data-state',
        'on',
    );
    await page.screenshot({
        path:
            testInfo.project.name === 'mobile-chromium'
                ? 'storage/app/orders-workspace-mobile-list.png'
                : 'storage/app/orders-workspace-desktop-list.png',
        fullPage: true,
    });
});

test('tabs y filtros avanzados conservan semántica en la URL', async ({
    page,
}) => {
    await logIn(page);
    await page.goto('/admin/orders');

    await page.getByRole('button', { name: 'En curso' }).click();
    await expect(page).toHaveURL(/group=in_progress/);
    await expect(
        page.getByRole('button', { name: 'En curso' }),
    ).toHaveAttribute('aria-current', 'page');

    await page.getByRole('button', { name: 'Abrir filtros y orden' }).click();
    await page.getByLabel('Filtrar por estado exacto').click();
    await page.getByRole('option', { name: 'Entregado' }).click();
    await page.getByLabel('Ordenar pedidos por').click();
    await page.getByRole('option', { name: 'Total' }).click();
    await page.getByLabel('Dirección del orden').click();
    await page.getByRole('option', { name: 'Ascendente' }).click();
    await page.getByRole('button', { name: 'Aplicar filtros' }).click();

    await expect(page).toHaveURL(/status=delivered/);
    await expect(page).toHaveURL(/group=delivered/);
    await expect(page).toHaveURL(/sort=total/);
    await expect(page).toHaveURL(/direction=asc/);
    await expect(
        page.getByRole('button', { name: 'Entregados' }),
    ).toHaveAttribute('aria-current', 'page');

    await page.getByRole('button', { name: 'Todos', exact: true }).click();
    await expect(page).not.toHaveURL(/status=/);
    await expect(page).not.toHaveURL(/group=/);
    await expect(page).toHaveURL(/sort=total/);
});

test('detalle compartido prioriza el ticket y copia el resumen estructurado', async ({
    page,
}, testInfo) => {
    test.skip(
        testInfo.project.name !== 'chromium',
        'Desktop valida la identidad del ticket compartido; mobile tiene su propia captura responsive.',
    );
    await logIn(page);
    const customerName = scenarioCustomerName(
        testInfo,
        'Cliente detalle Playwright',
    );
    const orderId = await createDefaultCookedOrder(page, customerName);
    await page.goto(`/admin/orders?search=${orderId}`);
    const orderButton = orderCardForId(page, orderId).getByRole('button', {
        name: orderId,
        exact: true,
    });
    await orderButton.click();

    const ticket = page.getByTestId('order-ticket-dialog');
    await expect(ticket).toContainText(orderId);
    await captureCopiedOrderDetail(page);
    await ticket.getByRole('button', { name: 'Copiar detalle' }).click();
    await expect(
        ticket.getByRole('button', { name: 'Detalle copiado' }),
    ).toBeVisible();
    const copied = await copiedOrderDetail(page);
    expect(copied).toContain(`🧾 *PEDIDO ${orderId}*`);
    expect(copied).toContain(`👤 *Cliente:* ${customerName}`);
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

    if (testInfo.project.name === 'chromium') {
        await configureOrderPaymentWallet(
            page,
            `Caja pedidos ${testInfo.project.name}`,
        );
    }

    const customerName = scenarioCustomerName(
        testInfo,
        'Cliente operación Playwright',
    );
    const orderId = await createOrder(page, customerName);
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
        const insightsProducts = await page
            .getByTestId('orders-insights-products')
            .boundingBox();
        const insightsDelivery = await page
            .getByTestId('orders-insights-delivery')
            .boundingBox();
        const summaryCards = page.getByTestId('order-summary-card');

        const sidebar = await page.locator('aside.admin-sidebar').boundingBox();
        const availableWidth =
            (page.viewportSize()?.width ?? 0) - (sidebar?.width ?? 0) - 48;
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
            (await summaryCards.nth(0).boundingBox())?.height,
        ).toBeLessThanOrEqual(110);
        expect(
            (insightsProducts?.height ?? 0) / (insightsDelivery?.height ?? 1),
        ).toBeCloseTo(429 / 333, 1);
        expect(
            (insightsDelivery?.y ?? 0) -
                (insightsProducts?.y ?? 0) -
                (insightsProducts?.height ?? 0),
        ).toBe(24);
        await expect(page.getByTestId('orders-report')).toHaveCount(0);
        await expect(page.getByTestId('orders-report-scroll')).toHaveCount(0);
        await expect(page.getByTestId('orders-results-scroll')).toHaveCSS(
            'overflow-y',
            'auto',
        );
        await expect(orderCardForId(page, orderId)).toBeVisible();
        expect(
            await page.evaluate(() => document.documentElement.scrollHeight),
        ).toBe(page.viewportSize()?.height);

        await page.screenshot({
            path: 'storage/app/orders-workspace-operational.png',
            fullPage: true,
        });
    }

    await page.goto(`/admin/orders?search=${orderId}`);
    const orderButton = orderCardForId(page, orderId).getByRole('button', {
        name: orderId,
        exact: true,
    });
    await orderButton.click();

    const ticket = page.getByTestId('order-ticket-dialog');
    await expect(ticket).toBeVisible();
    await expect(ticket).toContainText(orderId);
    await expect(ticket.getByText(customerName)).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`order=${orderId}`));

    if (testInfo.project.name === 'chromium') {
        await page.waitForTimeout(600);
        await page.screenshot({
            path: 'storage/app/orders-ticket-dialog.png',
            fullPage: true,
        });
    }

    await ticket.getByRole('button', { name: 'Cerrar' }).click();

    if (testInfo.project.name === 'mobile-chromium') {
        await expect(ticket).toBeHidden();

        return;
    }

    await page.getByLabel(`Estado de ${orderId}`).click();
    await page.getByRole('option', { name: 'Preparado' }).click();
    await expect(page.getByLabel(`Estado de ${orderId}`)).toHaveText(
        /Preparado/,
    );

    await page
        .getByRole('switch', { name: `Marcar como pagado ${orderId}` })
        .click();
    const paymentDialog = page.getByRole('dialog');
    await expect(paymentDialog).toContainText('Cobro del pedido');
    await expect(paymentDialog).toHaveCSS(
        'background-color',
        'rgb(255, 255, 255)',
    );
    await page.screenshot({
        path: 'storage/app/orders-payment-dialog.png',
        fullPage: true,
        animations: 'disabled',
    });
    await page.getByLabel(`Medio de pago de ${orderId}`).click();
    await expect(page.getByRole('listbox')).toHaveCSS(
        'background-color',
        'rgb(238, 241, 245)',
    );
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
    await expect(page.getByRole('heading', { name: orderId })).toBeVisible();
    await expect(page.getByText('Resumen del pedido')).toBeVisible();
    await expect(page.getByTestId('order-ticket-header')).toContainText(
        orderId,
    );
    await expect(
        page.getByRole('button', { name: 'Copiar detalle' }),
    ).toBeVisible();
});

test('ticket mantiene el resumen usable en viewport móvil', async ({
    page,
}, testInfo) => {
    await logIn(page);
    await page.goto('/admin/orders?search=TP-009001');
    await page.getByRole('button', { name: 'TP-009001', exact: true }).click();

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
            'El recorrido de escritura usa serialmente la base aislada de Playwright para desktop.',
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

        const rawPreview = page.waitForResponse((response) => {
            const request = response.request();

            return (
                request.method() === 'POST' &&
                new URL(response.url()).pathname ===
                    '/admin/orders/price-preview' &&
                response.ok()
            );
        });
        const raw = page.getByRole('checkbox', { name: 'Entregar crudas' });
        await expect(raw).not.toBeChecked();
        await raw.click();
        const rawQuote = (await (await rawPreview).json()) as {
            data: { raw_discount: number; products_cooked_subtotal: number };
        };
        expect(rawQuote.data.raw_discount).toBeGreaterThan(0);
        expect(rawQuote.data.products_cooked_subtotal).toBeGreaterThan(
            rawQuote.data.raw_discount,
        );
        await expect(
            page.getByText('Descuento por preparación en crudo'),
        ).toBeVisible();

        const updatedTotal = await summaryValue(
            page,
            'Total actualizado',
        ).textContent();
        expect(updatedTotal).not.toBe(originalTotal);
        await expect(summaryValue(page, 'Diferencia total')).toContainText(
            /[−-]/,
        );

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
        await expect(page.getByText('Crudas', { exact: true })).toBeVisible();
        await expect(
            page.getByText('Descuento por preparación en crudo'),
        ).toBeVisible();
        await expect(page.getByText(/recargo por cocción/i)).toHaveCount(0);

        await page.getByRole('link', { name: 'Editar pedido' }).click();
        await expect(page.getByText('Cantidad: 13')).toBeVisible();
        await expect(
            page.getByRole('checkbox', { name: 'Entregar crudas' }),
        ).toBeChecked();
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
        // This isolated scenario tests closed-order editing, not physical stock.
        // Use the supported explicit mode instead of bypassing delivery validation.
        await page.goto('/admin/settings/products');
        await page
            .getByRole('group', { name: 'Categorías de productos' })
            .getByRole('button', { name: 'Especiales', exact: true })
            .click();
        const product = page.locator('[data-slot="card"]').filter({
            has: page.getByRole('heading', { name: 'Salteña', exact: true }),
        });
        await product
            .getByRole('link', { name: 'Configurar consumo físico' })
            .click();
        await page
            .getByRole('combobox', { name: 'Estado', exact: true })
            .selectOption('no_inventory');
        await page
            .getByRole('button', { name: 'Guardar configuración', exact: true })
            .click();
        await expect(
            page.getByText(
                'Sin inventario explícito: costo físico conocido igual a cero.',
                { exact: true },
            ),
        ).toBeVisible();

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
        await expect(page.getByLabel('Estado del pedido')).toHaveText(
            'Entregado',
        );
        await expect(
            page.getByText('Nota corregida en pedido entregado'),
        ).toBeVisible();
        await expect(
            page.getByText('Pedido cerrado — edición bloqueada'),
        ).toBeVisible();
    });
});

test('Pedidos ofrece un solo Nuevo pedido y el formulario largo como modo avanzado', async ({
    page,
}) => {
    await logIn(page);
    await page.goto('/admin/orders');

    await expect(
        page.getByRole('link', { name: 'Nuevo pedido', exact: true }),
    ).toHaveAttribute('href', '/admin');
    await page
        .getByRole('link', { name: 'Modo avanzado', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/orders\/create$/);
    await expect(
        page.getByRole('heading', { level: 1, name: 'Modo avanzado' }),
    ).toBeVisible();
    await expect(page).toHaveTitle(/^Modo avanzado/);
    await expect(
        page.getByRole('heading', { name: 'Nuevo pedido' }),
    ).toHaveCount(0);
});
