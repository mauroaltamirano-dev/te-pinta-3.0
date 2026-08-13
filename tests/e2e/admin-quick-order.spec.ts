import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';

async function logIn(page: Page) {
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page.getByRole('button', { name: 'Log in' }).click();
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

test.describe.configure({ mode: 'serial' });
test.beforeEach(({ page }, testInfo) => {
    void page;
    test.skip(
        testInfo.project.name !== 'chromium',
        'The operational quick-order journey is covered at its desktop-priority viewport.',
    );
});

test('quick order edits the draft, previews backend pricing, and creates exactly one order', async ({
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
    await expect(dozen).toHaveText('12');
    await expect(halfDozen).toHaveText('6');
    await expect(unit).toHaveText('1');
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

    await expect(draft.getByText('Empanadas base')).toBeVisible();
    await expect(
        draft.getByText('Total', { exact: true }).first(),
    ).toBeVisible();
    await draft.getByRole('button', { name: 'Continuar' }).click();

    const continuation = page.getByRole('dialog');
    await expect(
        continuation.getByRole('heading', { name: 'Completar pedido' }),
    ).toBeVisible();
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

    const cooked = continuation.getByRole('button', { name: 'Cocinado' });
    await cooked.click();
    await expect(cooked).toHaveAttribute('aria-pressed', 'true');
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
    await continuation.getByRole('checkbox').click();
    await expect(
        continuation.getByText('Pagado', { exact: true }),
    ).toBeVisible();

    const confirm = continuation.getByRole('button', {
        name: 'Confirmar pedido',
    });
    await expect(confirm).toBeEnabled();
    await confirm.click();

    const orderHeading = continuation.getByRole('heading', { name: /TP-\d+/ });
    await expect(orderHeading).toBeVisible();
    const orderId = (await orderHeading.textContent())?.trim();
    expect(orderId).toMatch(/^TP-\d+$/);
    expect(orderPostCount()).toBe(1);

    await page.goto('/admin/orders');
    await expect(page.getByRole('button', { name: orderId })).toHaveCount(1);
});

test('closing or cancelling continuation keeps the draft and persists nothing', async ({
    page,
}) => {
    await logIn(page);
    const orderPostCount = countOrderPosts(page);

    await addProduct(page, 'Salteña', 2);
    await openDraftOnMobile(page);
    let draft = draftPanel(page);
    await expect(draft.getByText('Empanadas base')).toBeVisible();
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

test('discard clears the draft without persisting an order', async ({
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
