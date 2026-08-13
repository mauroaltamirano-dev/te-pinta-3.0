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

test.describe.configure({ mode: 'serial' });

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

test('lista abre un drawer operativo y conserva navegación completa', async ({
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

    const drawer = page.getByRole('dialog');
    await expect(drawer).toBeVisible();
    await expect(
        drawer.getByRole('heading', { name: orderId ?? /TP-/ }),
    ).toBeVisible();
    await expect(
        drawer.getByRole('link', { name: 'Cliente Playwright' }),
    ).toBeVisible();
    await expect(page).toHaveURL(new RegExp(`order=${orderId}`));

    if (testInfo.project.name === 'chromium') {
        await page.waitForTimeout(600);
        await page.screenshot({
            path: 'storage/app/orders-redesign-drawer.png',
            fullPage: true,
        });
    }

    await drawer.getByLabel('Estado del pedido').click();
    await page.getByRole('option', { name: 'Preparado' }).click();
    await expect(drawer.getByLabel('Estado del pedido')).toHaveText(
        /Preparado/,
    );

    await drawer.getByLabel('Estado del pago').click();
    await page.getByRole('option', { name: 'Pagado', exact: true }).click();
    await expect(drawer.getByLabel('Estado del pago')).toHaveText(/Pagado/);

    await drawer.getByRole('button', { name: 'Copiar detalle' }).click();
    await expect(
        drawer.getByRole('button', { name: 'Detalle copiado' }),
    ).toBeVisible();

    const fullDetail = drawer.getByRole('link', {
        name: 'Ver detalle completo',
    });
    await expect(fullDetail).toHaveAttribute(
        'href',
        `/admin/orders/${orderId}`,
    );

    await drawer.getByRole('button', { name: 'Close' }).click();
    await expect(drawer).toBeHidden();
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
});

test('drawer mantiene el detalle usable en viewport móvil', async ({
    page,
}, testInfo) => {
    await logIn(page);
    await page.goto('/admin/orders');
    await page.getByRole('button', { name: /TP-/ }).first().click();

    const drawer = page.getByRole('dialog');
    await expect(drawer).toBeVisible();
    const drawerWidth = (await drawer.boundingBox())?.width ?? 0;
    const viewportWidth = page.viewportSize()?.width ?? 0;
    expect(drawerWidth).toBeLessThanOrEqual(viewportWidth);

    if (testInfo.project.name === 'mobile-chromium') {
        expect(drawerWidth).toBe(viewportWidth);
    }

    await expect(drawer.getByText('Operaciones')).toBeVisible();
});
