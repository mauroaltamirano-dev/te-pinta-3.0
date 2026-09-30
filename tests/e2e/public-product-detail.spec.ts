import { expect, test } from '@playwright/test';

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

test('product detail is shareable and adds a selected quantity without blocking menu quick-add', async ({
    page,
}) => {
    await page.goto('/menu');
    const card = page.getByTestId('public-product-card').first();
    const title = await card.getByRole('heading').textContent();
    await card.getByRole('link', { name: 'Ver detalles' }).click();

    await expect(page).toHaveURL(/\/menu\/[^/]+$/);
    await page.reload();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(
        `${title}.`,
    );
    await expect(
        page.getByRole('region', { name: 'Precios cocinados' }),
    ).toBeVisible();
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
        'href',
        page.url(),
    );
    const shellColors = await page
        .locator('.public-shell')
        .evaluate((shell) => {
            const styles = getComputedStyle(shell);

            return {
                foreground: styles.color,
                background: styles.backgroundColor,
            };
        });
    expect(
        contrastRatio(shellColors.foreground, shellColors.background),
    ).toBeGreaterThanOrEqual(4.5);
    const categoryColors = await page
        .getByRole('heading', { level: 1 })
        .locator('xpath=preceding-sibling::p[1]')
        .evaluate((category) => {
            const styles = getComputedStyle(category);
            const shell = category.closest('.public-shell');

            return {
                foreground: styles.color,
                background: shell
                    ? getComputedStyle(shell).backgroundColor
                    : '',
            };
        });
    expect(categoryColors.foreground).toBe('rgb(255, 255, 255)');
    expect(
        contrastRatio(categoryColors.foreground, categoryColors.background),
    ).toBeGreaterThanOrEqual(4.5);

    if ((page.viewportSize()?.width ?? 0) < 640) {
        await page.setViewportSize({ width: 320, height: 700 });
        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            ),
        ).toBeLessThanOrEqual(1);
    }

    await page.getByRole('button', { name: 'Aumentar cantidad' }).click();
    await expect(page.getByLabel('Cantidad a agregar')).toHaveText('2');
    await page.getByRole('button', { name: 'Agregar 2 al carrito' }).click();
    await expect(page.getByText('2 en tu carrito')).toBeVisible();
    await expect(page.getByTestId('public-cart-fab')).toHaveAttribute(
        'aria-label',
        'Carrito, 2 productos',
    );

    await page.getByRole('link', { name: 'Volver al menú' }).click();
    await expect(
        page
            .getByTestId('public-product-card')
            .first()
            .getByText('En tu carrito: 2'),
    ).toBeVisible();
    await page
        .getByTestId('public-product-card')
        .first()
        .getByRole('button', { name: /Agregar una unidad de/ })
        .click();
    await expect(
        page
            .getByTestId('public-product-card')
            .first()
            .getByText('En tu carrito: 3'),
    ).toBeVisible();
    await expect(page.getByTestId('public-cart-fab')).toHaveAttribute(
        'aria-label',
        'Carrito, 3 productos',
    );
});
