import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

async function addSaltenaDozenAndOpenCart(page: Page) {
    await page.goto('/menu');
    const product = page
        .getByText('Salteña', { exact: true })
        .locator('xpath=ancestor::*[.//button[normalize-space()="Unidad"]][1]');
    await product.getByRole('button', { name: '+ Docena' }).click();
    await page.getByRole('link', { name: /Ver carrito/ }).click();
}

test('checkout público hidrata la fecha mínima desde options.today', async ({
    page,
}) => {
    await page.route('**/checkout/options', async (route) => {
        await route.fulfill({
            json: { data: { addons: [], today: '2026-08-13' } },
        });
    });
    await addSaltenaDozenAndOpenCart(page);
    await expect(page.getByLabel('Fecha de entrega')).toHaveValue('2026-08-13');
    await expect(page.getByLabel('Fecha de entrega')).toHaveAttribute(
        'min',
        '2026-08-13',
    );
});

test('checkout público usa cocinadas por defecto y crudas como alternativa más barata', async ({
    page,
}) => {
    await addSaltenaDozenAndOpenCart(page);

    const raw = page.getByRole('checkbox', { name: 'Pedir crudas' });
    await expect(raw).not.toBeChecked();
    await expect(page.getByText('Empanadas cocinadas')).toBeVisible();
    await expect(
        page.getByText('Descuento por preparación en crudo'),
    ).toHaveCount(0);
    const cookedSummary = page.getByRole('complementary');
    await expect(cookedSummary).toContainText(/18[.\s]000/);

    const rawPreview = page.waitForResponse((response) =>
        response.url().endsWith('/checkout/price-preview'),
    );
    await raw.click();
    const payload = (await (await rawPreview).json()) as {
        data: {
            products_cooked_subtotal: number;
            raw_discount: number;
            products_subtotal: number;
        };
    };

    expect(payload.data.products_cooked_subtotal).toBe(18000);
    expect(payload.data.raw_discount).toBe(2000);
    expect(payload.data.products_subtotal).toBe(16000);
    await expect(
        page.getByText('Descuento por preparación en crudo'),
    ).toBeVisible();
    await expect(cookedSummary).toContainText(/16[.\s]000/);
    await expect(page.getByText(/recargo por cocción/i)).toHaveCount(0);
});

test('checkout público persiste el pedido antes de abrir WhatsApp', async ({
    page,
}) => {
    await addSaltenaDozenAndOpenCart(page);
    await page.getByLabel('Nombre').fill('Cliente público');
    await page.getByLabel('Teléfono').fill('+54 9 11 5555 0000');
    await page.route('https://wa.me/**', async (route) =>
        route.fulfill({
            status: 200,
            contentType: 'text/html',
            body: '<h1>WhatsApp interceptado</h1>',
        }),
    );
    let createdOrder: {
        order_id: string;
        total: number;
        summary: string;
    } | null = null;
    await page.route('**/checkout/orders', async (route) => {
        const response = await route.fetch();
        const payload = (await response.json()) as {
            data: typeof createdOrder;
        };
        createdOrder = payload.data;
        await route.fulfill({ response });
    });
    await page
        .getByRole('button', { name: 'Confirmar y abrir WhatsApp' })
        .click();
    await expect.poll(() => createdOrder?.order_id).toMatch(/^TP-\d{6}$/);
    expect(createdOrder?.total).toBeGreaterThan(1);
    expect(createdOrder?.summary).toContain(createdOrder?.order_id);
    await expect(page).toHaveURL(/wa\.me/);
});

test('un preview fallido retira el total anterior y bloquea la confirmación hasta reintentar', async ({
    page,
}) => {
    await addSaltenaDozenAndOpenCart(page);
    await expect(page.getByTestId('order-total')).toBeVisible();

    let failNextPreview = true;
    await page.route('**/checkout/price-preview', async (route) => {
        if (failNextPreview) {
            failNextPreview = false;
            await route.fulfill({
                status: 503,
                json: { message: 'Cotización no disponible' },
            });

            return;
        }

        await route.continue();
    });

    await page.getByRole('checkbox', { name: 'Pedir crudas' }).click();
    await expect(page.getByRole('alert')).toContainText(
        'Cotización no disponible',
    );
    await expect(page.getByTestId('order-total')).toHaveCount(0);
    await expect(
        page.getByRole('button', { name: 'Confirmar y abrir WhatsApp' }),
    ).toBeDisabled();

    await page.getByRole('button', { name: 'Reintentar precio' }).click();
    await expect(page.getByTestId('order-total')).toBeVisible();
    await expect(page.getByRole('alert')).toHaveCount(0);
});

test('producto guardado que ya no está en el catálogo se identifica y se puede quitar', async ({
    page,
}) => {
    await page.goto('/menu');
    const product = page
        .getByText('Salteña', { exact: true })
        .locator('xpath=ancestor::*[.//button[normalize-space()="Unidad"]][1]');
    await product.getByRole('button', { name: '+ Docena' }).click();
    await page.route('**/menu/catalog', async (route) => {
        await route.fulfill({ json: { data: [] } });
    });
    await page.getByRole('link', { name: /Ver carrito/ }).click();

    await expect(page.getByText('Ya no disponible')).toBeVisible();
    await expect(page.getByRole('alert')).toContainText('Quitalos del carrito');
    await expect(
        page.getByRole('button', { name: 'Confirmar y abrir WhatsApp' }),
    ).toBeDisabled();
    await page.getByRole('button', { name: /^Quitar .* del carrito$/ }).click();
    await expect(page.getByText('Todavía no elegiste nada.')).toBeVisible();
});

test('si fallan las opciones iniciales, permite reintentar sin confirmar a ciegas', async ({
    page,
}) => {
    await page.goto('/menu');
    const product = page
        .getByText('Salteña', { exact: true })
        .locator('xpath=ancestor::*[.//button[normalize-space()="Unidad"]][1]');
    await product.getByRole('button', { name: '+ Docena' }).click();

    let failNextLoad = true;
    await page.route('**/checkout/options', async (route) => {
        if (failNextLoad) {
            failNextLoad = false;
            await route.fulfill({
                status: 503,
                json: { message: 'Unavailable' },
            });

            return;
        }

        await route.continue();
    });
    await page.getByRole('link', { name: /Ver carrito/ }).click();
    await expect(page.getByRole('alert')).toContainText(
        'No pudimos cargar el menú y las opciones',
    );
    await expect(
        page.getByRole('button', { name: 'Confirmar y abrir WhatsApp' }),
    ).toBeDisabled();
    await page.getByRole('button', { name: 'Reintentar carga' }).click();
    await expect(page.getByLabel('Fecha de entrega')).not.toBeEmpty();
    await expect(page.getByRole('alert')).toHaveCount(0);
});

test('el carrito a 320px mantiene controles y finalización sin desbordar', async ({
    page,
}) => {
    await page.setViewportSize({ width: 320, height: 720 });
    await addSaltenaDozenAndOpenCart(page);
    await expect(
        page.getByRole('heading', { name: 'Tu carrito.' }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Confirmar y abrir WhatsApp' }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
});
