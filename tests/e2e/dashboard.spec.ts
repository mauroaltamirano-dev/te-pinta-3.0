import type { Page, TestInfo } from '@playwright/test';
import type { DashboardData } from '@/features/dashboard/types';
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

async function waitForStableCapture(page: Page) {
    await page.evaluate(() => document.fonts.ready);
}

function dashboardScreenshot(testInfo: TestInfo) {
    return testInfo.project.name === 'mobile-chromium'
        ? 'storage/app/dashboard-mobile.png'
        : 'storage/app/dashboard-desktop.png';
}

function salesValue(page: Page, label: string) {
    return page
        .getByTestId('dashboard-kpis')
        .locator('article')
        .filter({
            has: page.getByRole('heading', { name: label, exact: true }),
        })
        .getByText(/^\$/);
}

test.beforeEach(async ({ page }) => {
    await logIn(page);
    await page.goto('/admin/dashboard');
});

test('dashboard muestra la operación real, estados vacíos y prioridad móvil', async ({
    page,
}, testInfo) => {
    await expect(
        page.getByRole('heading', { name: 'Panel', exact: true }),
    ).toBeVisible();
    const kpis = page.getByTestId('dashboard-kpis');
    await expect(kpis).toBeVisible();
    await expect(
        kpis.getByText('Ventas del día', { exact: true }),
    ).toBeVisible();
    await expect(
        kpis.getByText('Ingresos cobrados hoy', { exact: true }),
    ).toBeVisible();
    await expect(
        kpis.getByText('Pedidos de hoy', { exact: true }),
    ).toBeVisible();
    await expect(
        kpis.getByText('Por confirmar hoy', { exact: true }),
    ).toBeVisible();

    const urgent = page.getByTestId('dashboard-urgent-orders');
    const todayOrders = page.getByTestId('dashboard-today-orders');
    const exactTimeOrder = urgent.locator('li').filter({
        has: page.getByRole('button', { name: 'TP-009001', exact: true }),
    });
    const afternoonOrder = urgent.locator('li').filter({
        has: page.getByRole('button', { name: 'TP-009002', exact: true }),
    });
    const nightOrder = urgent.locator('li').filter({
        has: page.getByRole('button', { name: 'TP-009004', exact: true }),
    });

    await expect(exactTimeOrder).toContainText('11:00');
    await expect(afternoonOrder).toContainText('Tarde · Envío');
    await expect(nightOrder).toContainText('Noche · Retiro');
    await expect(
        todayOrders.getByRole('button', {
            name: 'TP-009004',
            exact: true,
        }),
    ).toBeVisible();

    await expect(page.getByTestId('dashboard-top-products')).toContainText(
        'Salteña',
    );
    await expect(page.getByTestId('dashboard-top-products')).toContainText(
        'Humita Tucumana',
    );
    await expect(page.getByTestId('dashboard-top-products')).toContainText(
        '12 unidades',
    );
    await expect(page.getByTestId('dashboard-top-products')).not.toContainText(
        /docena/i,
    );
    await expect(page.getByTestId('dashboard-distributions')).toContainText(
        'Crudas',
    );
    await expect(page.getByTestId('dashboard-distributions')).toContainText(
        'Cocinadas',
    );
    await expect(page.getByTestId('dashboard-distributions')).toContainText(
        'por unidades de productos',
    );
    await expect(page.getByTestId('dashboard-distributions')).not.toContainText(
        /empanadas/i,
    );
    await expect(page.getByText(/recargo por cocción/i)).toHaveCount(0);

    if (testInfo.project.name === 'mobile-chromium') {
        const urgentBox = await urgent.boundingBox();
        const todayBox = await todayOrders.boundingBox();
        const kpisBox = await page.getByTestId('dashboard-kpis').boundingBox();

        expect(urgentBox?.y ?? 0).toBeLessThan(todayBox?.y ?? 0);
        expect(todayBox?.y ?? 0).toBeLessThan(kpisBox?.y ?? 0);
        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            ),
        ).toBeLessThanOrEqual(1);
    }

    await waitForStableCapture(page);
    await page.screenshot({
        path: dashboardScreenshot(testInfo),
        fullPage: true,
        animations: 'disabled',
    });
});

test('hoy, semana, mes y desde siempre cambian los datos sin ocultar las urgencias de hoy', async ({
    page,
}) => {
    const urgent = page.getByTestId('dashboard-urgent-orders');
    const todaySales = await salesValue(page, 'Ventas del día').textContent();

    await page.getByRole('radio', { name: 'Mostrar período: Semana' }).click();
    await expect(page).toHaveURL(/period=week/);
    await expect(
        page.getByText('Ventas de la semana', { exact: true }),
    ).toBeVisible();
    const weekSales = await salesValue(
        page,
        'Ventas de la semana',
    ).textContent();
    expect(weekSales).not.toBe(todaySales);
    await expect(page.getByTestId('dashboard-top-products')).toContainText(
        'Salteña',
    );
    await expect(
        urgent.getByRole('button', { name: 'TP-009001', exact: true }),
    ).toBeVisible();

    await page.getByRole('radio', { name: 'Mostrar período: Mes' }).click();
    await expect(page).toHaveURL(/period=month/);
    await expect(
        page.getByText('Ventas del mes', { exact: true }),
    ).toBeVisible();
    const monthSales = await salesValue(page, 'Ventas del mes').textContent();
    expect(monthSales).not.toBe(todaySales);
    expect(monthSales).not.toBe(weekSales);
    await expect(
        urgent.getByRole('button', { name: 'TP-009001', exact: true }),
    ).toBeVisible();

    await page
        .getByRole('radio', { name: 'Mostrar período: Desde siempre' })
        .click();
    await expect(page).toHaveURL(/period=all/);
    await expect(
        page.getByText('Ventas desde siempre', { exact: true }),
    ).toBeVisible();
    await expect(
        urgent.getByRole('button', { name: 'TP-009001', exact: true }),
    ).toBeVisible();

    await page.getByRole('radio', { name: 'Mostrar período: Hoy' }).click();
    await expect(page).toHaveURL(/period=today/);
    await expect(
        page.getByTestId('dashboard-kpis').getByText('Ventas del día', {
            exact: true,
        }),
    ).toBeVisible();
});

test('dashboard presenta estados vacíos sin mutar los datos compartidos', async ({
    page,
}) => {
    await page.route('**/admin/dashboard?period=month', async (route) => {
        const response = await route.fetch();
        const payload = (await response.json()) as {
            props: { dashboard: DashboardData };
        };
        const dashboard = payload.props.dashboard;

        payload.props.dashboard = {
            ...dashboard,
            kpis: {
                sales_total: 0,
                collected_total: 0,
                orders_count: 0,
                pending_confirmation_count: 0,
                unpaid_count: 0,
                empanadas_count: 0,
            },
            today_status_distribution: dashboard.today_status_distribution.map(
                (item) => ({
                    ...item,
                    orders_count: 0,
                }),
            ),
            urgent_orders: [],
            today_orders: [],
            top_products: [],
            delivery_distribution: dashboard.delivery_distribution.map(
                (item) => ({ ...item, orders_count: 0 }),
            ),
            condition_distribution: dashboard.condition_distribution.map(
                (item) => ({ ...item, units_count: 0 }),
            ),
            evolution: dashboard.evolution.map((point) => ({
                ...point,
                total: 0,
            })),
        };

        await route.fulfill({ response, json: payload });
    });

    await page.getByRole('radio', { name: 'Mostrar período: Mes' }).click();
    await expect(page).toHaveURL(/period=month/);
    await expect(page.getByTestId('dashboard-urgent-orders')).toContainText(
        'No hay pedidos pendientes para hoy.',
    );
    await expect(page.getByTestId('dashboard-today-orders')).toContainText(
        'No hay pedidos para hoy.',
    );
    await expect(page.getByTestId('dashboard-top-products')).toContainText(
        'Todavía no hay productos vendidos en este período.',
    );
    await expect(page.getByTestId('dashboard-sales-evolution')).toContainText(
        'Todavía no se registraron ventas en este período.',
    );
    await expect(page.getByTestId('dashboard-distributions')).toContainText(
        'No hay pedidos para distribuir.',
    );
    await expect(page.getByTestId('dashboard-distributions')).toContainText(
        'No hay productos para distribuir.',
    );
});

test('un pedido urgente abre y cierra el detalle operativo compartido', async ({
    page,
}, testInfo) => {
    await page
        .getByTestId('dashboard-urgent-orders')
        .getByRole('button', { name: 'TP-009001', exact: true })
        .click();

    const sheet = page.getByTestId('order-detail-sheet');
    await expect
        .poll(() => new URL(page.url()).searchParams.get('period'))
        .toBe('today');
    await expect
        .poll(() => new URL(page.url()).searchParams.get('order'))
        .toBe('TP-009001');
    await expect(sheet).toBeVisible();
    await expect(sheet.getByText('Orden de producción')).toBeVisible();
    await expect(sheet.getByText('Operaciones')).toBeVisible();
    await expect(
        sheet.getByRole('link', { name: /Ver detalle completo/ }),
    ).toHaveAttribute('href', '/admin/orders/TP-009001');

    if (testInfo.project.name === 'chromium') {
        await waitForStableCapture(page);
        await page.screenshot({
            path: 'storage/app/dashboard-order-drawer.png',
            animations: 'disabled',
        });
    }

    await sheet.getByRole('button', { name: 'Cerrar' }).click();
    await expect(sheet).toHaveCount(0);
    await expect(page).toHaveURL(/\/admin\/dashboard\?period=today$/);
    await expect(
        page.getByRole('heading', { name: 'Panel', exact: true }),
    ).toBeVisible();
});

test('los accesos operativos navegan a pedidos y al Quick Order', async ({
    page,
}) => {
    await page
        .getByTestId('dashboard-today-orders')
        .getByRole('link', { name: /Ver todos/ })
        .click();
    await expect(page).toHaveURL(/\/admin\/orders$/);

    await page.goto('/admin/dashboard');
    await page.getByRole('link', { name: 'Nuevo pedido' }).click();
    await expect(page).toHaveURL(/\/admin$/);
});
