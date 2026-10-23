import { expect, test } from './support/test';

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

test('menu filters categories and keeps quick-add cart quantities accurate', async ({
    page,
}) => {
    await page.goto('/menu');
    await expect(page.getByTestId('public-cart-fab')).toHaveCount(0);
    await expect(page.getByTestId('mobile-cart-cta')).toHaveCount(0);
    await expect(
        page
            .getByRole('navigation', { name: 'Navegación pública' })
            .getByRole('link', { name: /Carrito/ }),
    ).toHaveCount(0);

    const cards = page.getByTestId('public-product-card');
    await expect(cards.first()).toBeVisible();
    const cardColors = await cards.first().evaluate((card) => {
        const styles = getComputedStyle(card);

        return { foreground: styles.color, background: styles.backgroundColor };
    });
    expect(cardColors).toEqual({
        foreground: 'rgb(27, 27, 27)',
        background: 'rgb(255, 255, 255)',
    });
    expect(
        contrastRatio(cardColors.foreground, cardColors.background),
    ).toBeGreaterThanOrEqual(4.5);
    const heroColors = await page.getByTestId('menu-hero').evaluate((hero) => ({
        foreground: getComputedStyle(hero).color,
        background: getComputedStyle(hero).backgroundColor,
    }));
    expect(heroColors).toEqual({
        foreground: 'rgb(255, 255, 255)',
        background: 'rgb(27, 27, 27)',
    });
    expect(
        contrastRatio(heroColors.foreground, heroColors.background),
    ).toBeGreaterThanOrEqual(4.5);
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
    const floatingCart = page.getByTestId('public-cart-fab');
    await expect(floatingCart).toHaveAttribute(
        'aria-label',
        'Carrito, 18 productos',
    );
    await expect(floatingCart).toContainText('18');

    if ((page.viewportSize()?.width ?? 0) < 640) {
        await page.setViewportSize({ width: 320, height: 700 });
        await expect(floatingCart).toBeVisible();
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
    }

    await floatingCart.click();
    await expect(page).toHaveURL(/\/cart$/);
    await expect(floatingCart).toHaveCount(0);
});

test('menu category divider spans the viewport and cart action remains reachable', async ({
    page,
}) => {
    await page.goto('/menu');
    const strip = page.getByTestId('menu-category-strip');
    await expect(strip).toBeVisible();

    for (const width of [1683, 390, 320]) {
        await page.setViewportSize({ width, height: 720 });
        const bounds = await strip.boundingBox();
        expect(bounds?.x).toBe(0);
        expect(bounds?.width).toBe(width);
        await expect(strip).toHaveCSS('border-bottom-width', '1px');
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth - window.innerWidth,
            ),
        ).toBeLessThanOrEqual(1);
    }

    await page
        .getByTestId('public-product-card')
        .first()
        .getByRole('button', { name: /Agregar una unidad de/ })
        .click();
    const floatingCart = page.getByTestId('public-cart-fab');

    for (const width of [1683, 390, 320]) {
        await page.setViewportSize({ width, height: 720 });
        const bounds = await floatingCart.boundingBox();
        expect(bounds).not.toBeNull();
        expect(bounds!.width).toBeGreaterThanOrEqual(44);
        expect(bounds!.height).toBeGreaterThanOrEqual(44);
        expect(bounds!.x).toBeGreaterThanOrEqual(0);
        expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(width);
        expect(bounds!.y + bounds!.height).toBeLessThanOrEqual(720);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth - window.innerWidth,
            ),
        ).toBeLessThanOrEqual(1);
    }

    await expect(floatingCart).toHaveAttribute(
        'aria-label',
        'Carrito, 1 producto',
    );
    await page.keyboard.press('Tab');
    await floatingCart.focus();
    await expect(floatingCart).toHaveCSS('outline-width', '2px');
    await floatingCart.click();
    await expect(page).toHaveURL(/\/cart$/);
    await expect(floatingCart).toHaveCount(0);
});

test('menu hides tier quick-add controls for a unit product', async ({
    page,
}) => {
    await page.goto('/menu');

    const product = page
        .getByTestId('public-product-card')
        .filter({ hasText: 'Bebida individual' });

    await expect(product).toBeVisible();
    await expect(
        product.getByRole('button', {
            name: 'Agregar una unidad de Bebida individual',
        }),
    ).toBeVisible();
    await expect(
        product.getByRole('button', { name: '+ Media docena' }),
    ).toHaveCount(0);
    await expect(product.getByRole('button', { name: '+ Docena' })).toHaveCount(
        0,
    );
});
