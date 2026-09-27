import { expect, test } from '@playwright/test';

test('menu filters categories and keeps quick-add cart quantities accurate', async ({
    page,
}) => {
    await page.goto('/menu');

    const cards = page.getByTestId('public-product-card');
    await expect(cards.first()).toBeVisible();
    const allCount = await cards.count();
    const categories = page
        .getByRole('group', { name: 'Categorías del menú' })
        .getByRole('button');
    expect(await categories.count()).toBeGreaterThan(1);

    const selectedCategory = categories.nth(1);
    const categoryName = await selectedCategory.textContent();
    await selectedCategory.click();
    await expect(selectedCategory).toHaveAttribute('aria-pressed', 'true');
    expect(await cards.count()).toBeGreaterThan(0);
    expect(await cards.count()).toBeLessThanOrEqual(allCount);

    for (const card of await cards.all()) {
        await expect(
            card.getByText(categoryName ?? '', { exact: true }),
        ).toBeVisible();
    }

    await categories.first().click();
    await expect(cards).toHaveCount(allCount);

    const product = cards.first();
    await product
        .getByRole('button', { name: /Agregar una unidad de/ })
        .click();
    await product.getByRole('button', { name: '+ Media docena' }).click();
    await product.getByRole('button', { name: '+ Docena' }).click();
    await expect(product.getByText('En tu carrito: 19')).toBeVisible();
    await product.getByRole('button', { name: /Quitar una unidad de/ }).click();
    await expect(product.getByText('En tu carrito: 18')).toBeVisible();
    await expect(
        page.getByRole('link', { name: /Carrito/ }).first(),
    ).toContainText('18');

    if ((page.viewportSize()?.width ?? 0) < 640) {
        await page.setViewportSize({ width: 320, height: 700 });
        await expect(
            page.getByRole('link', { name: 'Carrito, 18 productos' }),
        ).toBeVisible();
        const cartCta = page.getByTestId('mobile-cart-cta');
        await expect(cartCta).toBeVisible();
        await expect(cartCta).toContainText('18');
        const halfDozenButton = product.getByRole('button', {
            name: '+ Media docena',
        });
        expect(
            await halfDozenButton.evaluate(
                (button) => button.scrollWidth - button.clientWidth,
            ),
        ).toBeLessThanOrEqual(1);
        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            ),
        ).toBeLessThanOrEqual(1);
        await cartCta.click();
    } else {
        await page.getByRole('link', { name: /Ver carrito/ }).click();
    }

    await expect(page).toHaveURL(/\/cart$/);
});
