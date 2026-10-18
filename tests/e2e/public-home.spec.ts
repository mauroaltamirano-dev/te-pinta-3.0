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

test('public home presents the brand and leads directly to the menu', async ({
    page,
}) => {
    await page.goto('/');
    await expect(page.getByTestId('public-cart-fab')).toHaveCount(0);

    await expect(
        page.getByRole('heading', { name: /Te pinta una empanada/i }),
    ).toBeVisible();
    const orderCta = page.getByRole('link', { name: /Hacer pedido/i });
    await expect(orderCta).toHaveAttribute('href', '/menu');
    const orderCtaColors = await orderCta.evaluate((link) => {
        const styles = getComputedStyle(link);

        return {
            foreground: styles.color,
            background: styles.backgroundColor,
        };
    });
    expect(orderCtaColors).toEqual({
        foreground: 'rgb(255, 255, 255)',
        background: 'rgb(184, 79, 63)',
    });
    expect(
        contrastRatio(orderCtaColors.foreground, orderCtaColors.background),
    ).toBeGreaterThanOrEqual(4.5);
    await expect(
        page.getByRole('navigation', { name: 'Navegación pública' }),
    ).toBeVisible();

    const hero = page.getByTestId('public-hero-image');
    await expect(hero).toHaveAttribute('src', '/images/brand/hero.png');
    await expect
        .poll(() =>
            hero.evaluate((image: HTMLImageElement) => image.naturalWidth),
        )
        .toBeGreaterThan(0);
    await expect(
        page.getByRole('link', { name: 'Te Pinta, inicio' }).locator('img'),
    ).toHaveAttribute('src', '/images/brand/logo-completo-blanco.png');
    const footerLogo = page.locator('footer img');
    await expect(footerLogo).toHaveAttribute(
        'src',
        '/images/brand/logo-blanco.png',
    );
    await footerLogo.scrollIntoViewIfNeeded();
    await expect
        .poll(() =>
            footerLogo.evaluate(
                (image: HTMLImageElement) => image.naturalWidth,
            ),
        )
        .toBeGreaterThan(0);
    expect(
        await page
            .locator('main > section')
            .first()
            .evaluate((section) => getComputedStyle(section).borderBottomWidth),
    ).toBe('0px');

    const palette = await page.locator('.public-shell').evaluate((element) => {
        const styles = getComputedStyle(element);

        return {
            foreground: styles.color,
            canvas: styles.backgroundColor,
        };
    });
    expect(palette).toEqual({
        foreground: 'rgb(255, 255, 255)',
        canvas: 'rgb(184, 79, 63)',
    });
    expect(
        contrastRatio(palette.foreground, palette.canvas),
    ).toBeGreaterThanOrEqual(4.5);

    if ((page.viewportSize()?.width ?? 0) >= 640) {
        const activeNavigation = page.getByRole('link', {
            name: 'Inicio',
            exact: true,
        });
        const activeNavigationColors = await activeNavigation.evaluate(
            (link) => {
                const styles = getComputedStyle(link);

                return {
                    foreground: styles.color,
                    background: styles.backgroundColor,
                };
            },
        );
        expect(activeNavigationColors).toEqual({
            foreground: 'rgb(27, 27, 27)',
            background: 'rgb(241, 241, 241)',
        });
        expect(
            contrastRatio(
                activeNavigationColors.foreground,
                activeNavigationColors.background,
            ),
        ).toBeGreaterThanOrEqual(4.5);
    } else {
        const mobileMenu = page.getByRole('link', {
            name: 'Menú',
            exact: true,
        });
        const mobileMenuColors = await mobileMenu.evaluate((link) => {
            const shell = link.closest('.public-shell');

            return {
                foreground: getComputedStyle(link).color,
                background: shell
                    ? getComputedStyle(shell).backgroundColor
                    : '',
            };
        });
        expect(mobileMenuColors).toEqual({
            foreground: 'rgb(255, 255, 255)',
            background: 'rgb(184, 79, 63)',
        });
        expect(
            contrastRatio(
                mobileMenuColors.foreground,
                mobileMenuColors.background,
            ),
        ).toBeGreaterThanOrEqual(4.5);
    }

    await expect(
        page.locator('section[aria-labelledby="home-menu-title"]'),
    ).toHaveCSS('background-color', 'rgb(241, 241, 241)');

    if (page.viewportSize()?.width && page.viewportSize()!.width < 640) {
        await page.setViewportSize({ width: 320, height: 700 });
        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            ),
        ).toBeLessThanOrEqual(1);
    }

    await page.getByRole('link', { name: /Hacer pedido/i }).click();
    await expect(page).toHaveURL(/\/menu$/);
    await page
        .getByTestId('public-product-card')
        .first()
        .getByRole('button', { name: /Agregar una unidad de/ })
        .click();
    await page.getByRole('link', { name: 'Te Pinta, inicio' }).click();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByTestId('public-cart-fab')).toHaveAttribute(
        'aria-label',
        'Carrito, 1 producto',
    );
});
