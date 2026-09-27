import { expect, test } from '@playwright/test';

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
});
