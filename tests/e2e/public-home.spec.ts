import { expect, test } from '@playwright/test';

test('public home presents the brand and leads directly to the menu', async ({
    page,
}) => {
    await page.goto('/');

    await expect(
        page.getByRole('heading', { name: /Se te pinta una empanada/i }),
    ).toBeVisible();
    await expect(
        page.getByRole('link', { name: /Hacer pedido/i }),
    ).toHaveAttribute('href', '/menu');
    await expect(
        page.getByRole('navigation', { name: 'Navegación pública' }),
    ).toBeVisible();

    const palette = await page.locator('.public-shell').evaluate((element) => {
        const styles = getComputedStyle(element);
        const button = element.querySelector('main a[href="/menu"]');

        return {
            ink: styles.color,
            canvas: styles.backgroundColor,
            earth: button ? getComputedStyle(button).backgroundColor : null,
        };
    });
    expect(palette).toEqual({
        ink: 'rgb(51, 51, 51)',
        canvas: 'rgb(227, 227, 227)',
        earth: 'rgb(184, 92, 63)',
    });

    if (page.viewportSize()?.width && page.viewportSize()!.width < 640) {
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
});
