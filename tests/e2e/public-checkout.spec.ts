import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

function contrastRatio(foreground: string, background: string) {
    const luminance = (color: string) => {
        const channels = color.match(/\d+(?:\.\d+)?/g)?.map(Number) ?? [];
        const [red, green, blue] = channels.map((channel) => {
            const value = channel / 255;

            return value <= 0.04045
                ? value / 12.92
                : ((value + 0.055) / 1.055) ** 2.4;
        });

        return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
    };
    const [light, dark] = [luminance(foreground), luminance(background)].sort(
        (first, second) => second - first,
    );

    return (light + 0.05) / (dark + 0.05);
}

async function addSaltenaDozenAndOpenCart(page: Page) {
    await page.goto('/menu');
    const heroArtwork = page.getByTestId('menu-hero-artwork');
    await expect(heroArtwork).toHaveAttribute(
        'src',
        '/images/brand/bg-empanada.png',
    );
    await expect(heroArtwork).toHaveAttribute('alt', '');
    await expect(heroArtwork).toHaveAttribute('aria-hidden', 'true');
    await expect(heroArtwork).toHaveCSS('pointer-events', 'none');
    await expect(heroArtwork).toHaveCSS('user-select', 'none');
    const product = page
        .getByText('Salteña', { exact: true })
        .locator('xpath=ancestor::*[.//button[normalize-space()="Unidad"]][1]');
    await product.getByRole('button', { name: '+ Docena' }).click();
    await page.getByTestId('menu-hero-cart-cta').click();
}

test('menu artwork is served as a PNG', async ({ request }) => {
    const response = await request.get('/images/brand/bg-empanada.png');

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('image/png');
    expect((await response.body()).length).toBeGreaterThan(0);
});

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
    const cardColors = await page
        .locator('[data-slot="card"]')
        .first()
        .evaluate((card) => {
            const styles = getComputedStyle(card);

            return {
                foreground: styles.color,
                background: styles.backgroundColor,
            };
        });
    expect(
        contrastRatio(cardColors.foreground, cardColors.background),
    ).toBeGreaterThanOrEqual(4.5);
    const labelColors = await page
        .getByText('Tu pedido · Paso final')
        .evaluate((label) => {
            const styles = getComputedStyle(label);
            const shell = label.closest('.public-shell');

            return {
                foreground: styles.color,
                background: shell
                    ? getComputedStyle(shell).backgroundColor
                    : '',
            };
        });
    expect(labelColors.foreground).toBe('rgb(255, 255, 255)');
    expect(
        contrastRatio(labelColors.foreground, labelColors.background),
    ).toBeGreaterThanOrEqual(4.5);
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

test('un preview exitoso al volver a una selección elimina el error anterior de esa selección', async ({
    page,
}) => {
    await addSaltenaDozenAndOpenCart(page);
    await expect(page.getByTestId('order-total')).toBeVisible();

    let failFirstRawPreview = true;
    await page.route('**/checkout/price-preview', async (route) => {
        const body = route.request().postDataJSON() as { condition: string };

        if (body.condition === 'raw' && failFirstRawPreview) {
            failFirstRawPreview = false;
            await route.fulfill({
                status: 503,
                json: { message: 'Cotización no disponible' },
            });

            return;
        }

        await route.continue();
    });

    const raw = page.getByRole('checkbox', { name: 'Pedir crudas' });
    await raw.click();
    await expect(page.getByRole('alert')).toContainText(
        'Cotización no disponible',
    );
    await expect(page.getByTestId('order-total')).toHaveCount(0);

    const cookedAgain = page.waitForResponse((response) =>
        response.url().endsWith('/checkout/price-preview'),
    );
    await raw.click();
    expect((await cookedAgain).ok()).toBe(true);
    await expect(page.getByTestId('order-total')).toBeVisible();

    const rawAgain = page.waitForResponse((response) =>
        response.url().endsWith('/checkout/price-preview'),
    );
    await raw.click();
    expect((await rawAgain).ok()).toBe(true);
    await expect(page.getByRole('alert')).toHaveCount(0);
    await expect(page.getByTestId('order-total')).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Confirmar y abrir WhatsApp' }),
    ).toBeEnabled();
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
    await page.getByTestId('menu-hero-cart-cta').click();

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
    await page.getByTestId('menu-hero-cart-cta').click();
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
