import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

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

async function addProduct(page: Page, name: string, times = 1) {
    const unitButton = page
        .getByRole('button', {
            name: `Agregar 1 unidad de ${name}`,
            exact: true,
        })
        .first();

    for (let click = 0; click < times; click += 1) {
        await unitButton.click();
    }
}

async function openDraftOnMobile(page: Page) {
    if ((page.viewportSize()?.width ?? 0) < 1024) {
        await page.getByRole('button', { name: /^Pedido/ }).click();
    }
}

function draftPanel(page: Page): Page | Locator {
    return (page.viewportSize()?.width ?? 0) < 1024
        ? page.getByRole('dialog')
        : page.locator('aside').filter({ hasText: 'Pedido actual' });
}

function countOrderPosts(page: Page) {
    let count = 0;
    page.on('request', (request) => {
        if (
            request.method() === 'POST' &&
            new URL(request.url()).pathname === '/admin/orders'
        ) {
            count += 1;
        }
    });

    return () => count;
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

async function changeDraftPricing(
    page: Page,
    action: () => Promise<void>,
): Promise<{
    products_cooked_subtotal: number;
    raw_discount: number;
    discountable_subtotal: number;
    discount_amount: number;
    shipping_fee: number;
    total: number;
}> {
    const preview = page.waitForResponse((response) => {
        const request = response.request();

        return (
            request.method() === 'POST' &&
            new URL(response.url()).pathname ===
                '/admin/orders/price-preview' &&
            response.ok()
        );
    });

    await action();

    return (
        (await (await preview).json()) as {
            data: {
                products_cooked_subtotal: number;
                raw_discount: number;
                discountable_subtotal: number;
                discount_amount: number;
                shipping_fee: number;
                total: number;
            };
        }
    ).data;
}

test.describe.configure({ mode: 'serial' });
test.beforeEach(({ page }, testInfo) => {
    void page;
    test.skip(
        testInfo.project.name !== 'chromium',
        'El recorrido operativo de Quick Order se cubre en el viewport prioritario de escritorio.',
    );
});

test('Quick Order edita el borrador, previsualiza pricing del backend y crea exactamente un pedido', async ({
    page,
}) => {
    await logIn(page);
    const orderPostCount = countOrderPosts(page);

    const dozen = page.getByRole('button', {
        name: 'Agregar 12 unidades de Salteña',
    });
    const halfDozen = page.getByRole('button', {
        name: 'Agregar 6 unidades de Salteña',
    });
    const unit = page.getByRole('button', {
        name: 'Agregar 1 unidad de Salteña',
    });
    const productCard = dozen.locator('xpath=ancestor::article[1]');

    await expect(productCard).toContainText('Desde · docena cocinada');
    await expect(productCard).toContainText('$ 18.000');
    await expect(dozen).toHaveText('12');
    await expect(halfDozen).toHaveText('6');
    await expect(unit).toHaveText('1');
    await expect(dozen).toHaveCSS(
        'background-color',
        'rgba(234, 124, 105, 0.24)',
    );
    expect(
        await dozen.evaluate((button) => {
            const card = button.closest('article');

            return Boolean(
                card &&
                button.getBoundingClientRect().bottom <=
                    card.getBoundingClientRect().bottom,
            );
        }),
    ).toBe(true);
    await page.screenshot({
        path: 'storage/app/admin-quick-order-catalog.png',
        fullPage: true,
        animations: 'disabled',
    });
    await dozen.click();
    await halfDozen.click();
    await unit.click();
    await addProduct(page, 'Humita Tucumana');
    await openDraftOnMobile(page);
    const draft = draftPanel(page);

    await expect(draft.getByLabel('Cantidad de Salteña: 19')).toBeVisible();
    await draft
        .getByRole('button', { name: 'Quitar una unidad de Salteña' })
        .click();
    await expect(draft.getByLabel('Cantidad de Salteña: 18')).toBeVisible();
    await draft
        .getByRole('button', {
            name: 'Agregar una unidad de Salteña',
            exact: true,
        })
        .click();
    await expect(draft.getByLabel('Cantidad de Salteña: 19')).toBeVisible();
    await draft
        .getByRole('button', { name: 'Eliminar Humita Tucumana del pedido' })
        .click();
    await expect(
        draft.getByLabel('Cantidad de Humita Tucumana: 1'),
    ).toHaveCount(0);

    await expect(draft.getByText('Empanadas cocinadas')).toBeVisible();
    await expect(
        draft.getByText('Total', { exact: true }).first(),
    ).toBeVisible();
    await draft.getByRole('button', { name: 'Continuar' }).click();

    const continuation = page.getByRole('dialog');
    await expect(
        continuation.getByRole('heading', { name: 'Completar pedido' }),
    ).toBeVisible();
    const dateTrigger = continuation.getByRole('button', {
        name: /\d{2}\/\d{2}\/\d{4}/,
    });
    await dateTrigger.click();
    const calendar = page.locator('[data-slot="popover-content"]');
    await expect(calendar).toHaveCSS('background-color', 'rgb(45, 48, 62)');
    await expect(calendar.locator('[data-selected-single="true"]')).toHaveCSS(
        'background-color',
        'rgb(234, 124, 105)',
    );
    await page.screenshot({
        path: 'storage/app/admin-order-calendar.png',
        fullPage: true,
        animations: 'disabled',
    });
    await page.keyboard.press('Escape');

    const deliveryPeriod = continuation.getByLabel('Franja');
    await expect(deliveryPeriod).toHaveCSS(
        'background-color',
        'rgb(37, 40, 54)',
    );
    await deliveryPeriod.click();
    await expect(page.getByRole('listbox')).toHaveCSS(
        'background-color',
        'rgb(45, 48, 62)',
    );
    await page.getByRole('option', { name: 'Noche', exact: true }).click();

    const customerSearch = continuation.getByLabel('Buscar cliente');
    await customerSearch.fill('5555');
    await expect(
        continuation.getByRole('button', { name: /Cliente habitual/ }),
    ).toBeVisible();
    await customerSearch.fill('habitual');
    const customerMatch = continuation.getByRole('button', {
        name: /Cliente habitual/,
    });
    await expect(customerMatch).toBeVisible();
    await customerMatch.click();

    const raw = continuation.getByRole('checkbox', {
        name: 'Entregar crudas',
    });
    await expect(raw).not.toBeChecked();
    await expect(continuation.getByText('Empanadas cocinadas')).toBeVisible();
    const rawQuote = await changeDraftPricing(page, () => raw.click());
    await expect(raw).toBeChecked();
    expect(rawQuote.products_cooked_subtotal).toBeGreaterThan(0);
    expect(rawQuote.raw_discount).toBeGreaterThan(0);
    await expect(
        continuation.getByText('Descuento por preparación en crudo'),
    ).toBeVisible();
    await expect(continuation.getByText(/recargo por cocción/i)).toHaveCount(0);
    await page.screenshot({
        path: 'storage/app/admin-quick-order-continuation.png',
        fullPage: true,
        animations: 'disabled',
    });
    await continuation
        .getByRole('button', { name: 'Agregar un adicional Lactonesa' })
        .click();
    await continuation.getByRole('button', { name: 'Envío' }).click();
    await expect(continuation.getByLabel('Dirección de entrega')).toHaveValue(
        'Av. Siempre Viva 123',
    );
    await continuation
        .getByLabel('Dirección de entrega')
        .fill('Calle del pedido 456');
    await continuation.getByRole('button', { name: 'Retiro en local' }).click();
    await continuation.getByRole('button', { name: 'Envío' }).click();
    await expect(continuation.getByLabel('Dirección de entrega')).toHaveValue(
        'Calle del pedido 456',
    );

    await changeDraftPricing(page, () =>
        continuation
            .getByText('Aplicar descuento', { exact: true })
            .locator('..')
            .getByRole('checkbox')
            .click(),
    );
    const percentageQuote = await changeDraftPricing(page, () =>
        continuation.getByLabel('Porcentaje', { exact: true }).fill('10'),
    );
    expect(percentageQuote.discount_amount).toBe(
        Math.round(percentageQuote.discountable_subtotal * 0.1),
    );
    expect(percentageQuote.total).toBe(
        percentageQuote.discountable_subtotal -
            percentageQuote.discount_amount +
            percentageQuote.shipping_fee,
    );
    await expect(
        continuation.getByText(
            'Se aplica a productos y adicionales. El envío no se descuenta.',
        ),
    ).toBeVisible();

    await changeDraftPricing(page, () =>
        continuation.getByRole('button', { name: 'Retiro en local' }).click(),
    );
    const zeroTotalQuote = await changeDraftPricing(page, () =>
        continuation.getByLabel('Porcentaje', { exact: true }).fill('100'),
    );
    expect(zeroTotalQuote.total).toBe(0);
    const noPaymentRequired = continuation
        .getByText('No requiere pago — total $0')
        .locator('..');
    await expect(noPaymentRequired.getByRole('checkbox')).toBeDisabled();

    await changeDraftPricing(page, () =>
        continuation.getByRole('button', { name: 'Envío' }).click(),
    );
    await changeDraftPricing(page, () =>
        continuation.getByLabel('Porcentaje', { exact: true }).fill('10'),
    );

    await changeDraftPricing(page, async () => {
        await continuation.getByLabel('Tipo de descuento').click();
        await expect(page.getByRole('listbox')).toHaveCSS(
            'background-color',
            'rgb(45, 48, 62)',
        );
        await page
            .getByRole('option', { name: 'Monto fijo', exact: true })
            .click();
    });
    const fixedQuote = await changeDraftPricing(page, () =>
        continuation.getByLabel('Monto en pesos').fill('1000'),
    );
    expect(fixedQuote.discount_amount).toBe(1000);
    expect(fixedQuote.total).toBe(
        fixedQuote.discountable_subtotal - 1000 + fixedQuote.shipping_fee,
    );

    await continuation
        .getByText('No pagado', { exact: true })
        .locator('..')
        .getByRole('checkbox')
        .click();
    await expect(
        continuation.getByText('Pagado', { exact: true }),
    ).toBeVisible();
    const confirm = continuation.getByRole('button', {
        name: 'Confirmar pedido',
    });
    await expect(confirm).toBeDisabled();
    await continuation.getByLabel('Medio de pago').click();
    await expect(page.getByRole('listbox')).toHaveCSS(
        'background-color',
        'rgb(45, 48, 62)',
    );
    await page.getByRole('option', { name: 'Efectivo', exact: true }).click();
    await expect(confirm).toBeEnabled();
    await continuation
        .getByText('Pagado', { exact: true })
        .locator('..')
        .getByRole('checkbox')
        .click();
    await expect(continuation.getByLabel('Medio de pago')).toHaveCount(0);
    await expect(confirm).toBeEnabled();
    await continuation
        .getByText('No pagado', { exact: true })
        .locator('..')
        .getByRole('checkbox')
        .click();
    await expect(confirm).toBeDisabled();
    await continuation.getByLabel('Medio de pago').click();
    await page
        .getByRole('option', { name: 'Transferencia', exact: true })
        .click();
    await expect(confirm).toBeEnabled();
    await confirm.click();

    const orderHeading = continuation.getByRole('heading', { name: /TP-\d+/ });
    await expect(orderHeading).toBeVisible();
    const orderId = (await orderHeading.textContent())?.trim();
    expect(orderId).toMatch(/^TP-\d+$/);
    expect(orderPostCount()).toBe(1);
    await expect(
        continuation.getByTestId('quick-order-success-ticket'),
    ).toContainText(orderId ?? '');
    await captureCopiedOrderDetail(page);
    await continuation.getByRole('button', { name: 'Copiar detalle' }).click();
    await expect(
        continuation.getByRole('button', { name: 'Detalle copiado' }),
    ).toBeVisible();
    const copied = await page.evaluate(
        () =>
            (window as Window & { copiedOrderDetail?: string })
                .copiedOrderDetail ?? '',
    );
    expect(copied).toContain(`🧾 *PEDIDO ${orderId}*`);
    expect(copied).toContain('👤 *Cliente:* Cliente habitual');
    await expect(
        continuation.getByRole('link', { name: 'Ver pedido' }),
    ).toHaveAttribute('href', `/admin/orders/${orderId}`);
    await page.screenshot({
        path: 'storage/app/admin-quick-order-success.png',
        fullPage: true,
    });

    await page.goto('/admin/orders');
    await expect(page.getByRole('button', { name: orderId })).toHaveCount(1);
});

test('cerrar o cancelar la continuación conserva el borrador y no persiste datos', async ({
    page,
}) => {
    await logIn(page);
    const orderPostCount = countOrderPosts(page);

    await addProduct(page, 'Salteña', 2);
    await openDraftOnMobile(page);
    let draft = draftPanel(page);
    await expect(draft.getByText('Empanadas cocinadas')).toBeVisible();
    await draft.getByRole('button', { name: 'Continuar' }).click();
    await page
        .getByRole('dialog')
        .getByLabel('Nombre')
        .fill('No persistir cierre');
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Volver al catálogo' })
        .click();

    await openDraftOnMobile(page);
    draft = draftPanel(page);
    await expect(draft.getByLabel('Cantidad de Salteña: 2')).toBeVisible();
    await draft.getByRole('button', { name: 'Continuar' }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Cancelar' })
        .click();

    await openDraftOnMobile(page);
    draft = draftPanel(page);
    await expect(draft.getByLabel('Cantidad de Salteña: 2')).toBeVisible();
    expect(orderPostCount()).toBe(0);
    await page.goto('/admin/orders');
    await expect(page.getByText('No persistir cierre')).toHaveCount(0);
});

test('la dirección del cliente nuevo se sincroniza sin sobrescribir una dirección manual del pedido', async ({
    page,
}) => {
    await logIn(page);
    await addProduct(page, 'Salteña');
    await openDraftOnMobile(page);
    await draftPanel(page).getByRole('button', { name: 'Continuar' }).click();

    const continuation = page.getByRole('dialog');
    await continuation.getByRole('button', { name: 'Envío' }).click();
    const customerAddress = continuation.getByRole('textbox', {
        name: 'Dirección',
        exact: true,
    });
    await customerAddress.pressSequentially('San Martín 1234');
    await expect(continuation.getByLabel('Dirección de entrega')).toHaveValue(
        'San Martín 1234',
    );

    await continuation
        .getByLabel('Dirección de entrega')
        .fill('Dirección exclusiva del pedido');
    await customerAddress.fill('');
    await customerAddress.pressSequentially('San Martín 9999');
    await expect(continuation.getByLabel('Dirección de entrega')).toHaveValue(
        'Dirección exclusiva del pedido',
    );
});

test('descartar limpia el borrador sin persistir un pedido', async ({
    page,
}) => {
    await logIn(page);
    const orderPostCount = countOrderPosts(page);

    await addProduct(page, 'Salteña');
    await openDraftOnMobile(page);
    const draft = draftPanel(page);
    await expect(draft.getByLabel('Cantidad de Salteña: 1')).toBeVisible();
    await draft.getByRole('button', { name: 'Descartar pedido' }).click();

    await expect(draft.getByText('El pedido está vacío')).toBeVisible();
    await expect(
        draft.getByRole('button', { name: 'Continuar' }),
    ).toBeDisabled();
    expect(orderPostCount()).toBe(0);
});
