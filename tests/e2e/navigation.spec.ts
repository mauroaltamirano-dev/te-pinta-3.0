import { readFile } from 'node:fs/promises';
import { expect, test } from './support/test';

test('la marca del panel de autenticación conserva contraste en ambos temas', async ({
    page,
}) => {
    const layout = await readFile(
        new URL(
            '../../resources/js/layouts/auth/auth-split-layout.tsx',
            import.meta.url,
        ),
        'utf8',
    );

    const panelClasses = layout.match(
        /<div className="([^"]*\blg:flex\b[^"]*)">/,
    )?.[1];
    const overlayClasses = layout.match(
        /<div className="([^"]*\bbg-zinc-900\b[^"]*)"/,
    )?.[1];
    const brandLink = layout.match(/<Link[\s\S]*?<\/Link>/)?.[0];
    const brandClasses = brandLink?.match(/className="([^"]+)"/)?.[1];
    const logoClasses = brandLink?.match(
        /<AppLogoIcon className="([^"]+)"/,
    )?.[1];
    expect(panelClasses).toContain('text-white');
    expect(overlayClasses).toContain('bg-zinc-900');
    expect(brandClasses).toContain('text-white');
    expect(logoClasses).toContain('text-white');

    await page.goto('/login');

    for (const colorScheme of ['light', 'dark'] as const) {
        await page.emulateMedia({ colorScheme });
        await page.locator('html').evaluate((element, scheme) => {
            element.classList.toggle('dark', scheme === 'dark');
        }, colorScheme);

        const colors = await page.evaluate(
            ({ panelClasses, overlayClasses, brandClasses, logoClasses }) => {
                const panel = document.createElement('section');
                panel.className = panelClasses;
                const overlay = document.createElement('div');
                overlay.className = overlayClasses;
                const name = document.createElement('span');
                name.className = brandClasses;
                name.textContent = 'Te Pinta';
                const logo = document.createElementNS(
                    'http://www.w3.org/2000/svg',
                    'svg',
                );
                logo.setAttribute('class', logoClasses);
                panel.append(overlay, name, logo);
                document.body.append(panel);

                const background = getComputedStyle(overlay).backgroundColor;
                const foreground = getComputedStyle(name).color;
                const logoColor = getComputedStyle(logo).color;
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d')!;
                const luminance = (color: string) => {
                    context.fillStyle = color;
                    context.fillRect(0, 0, 1, 1);

                    const [red, green, blue] = Array.from(
                        context.getImageData(0, 0, 1, 1).data.slice(0, 3),
                        (channel) => channel / 255,
                    );

                    return [red, green, blue]
                        .map((channel) =>
                            channel <= 0.04045
                                ? channel / 12.92
                                : ((channel + 0.055) / 1.055) ** 2.4,
                        )
                        .reduce(
                            (sum, channel, index) =>
                                sum + channel * [0.2126, 0.7152, 0.0722][index],
                            0,
                        );
                };
                const first = luminance(foreground);
                const second = luminance(background);
                const logoLuminance = luminance(logoColor);

                panel.remove();

                return {
                    foreground,
                    background,
                    logoColor,
                    ratio:
                        (Math.max(first, second) + 0.05) /
                        (Math.min(first, second) + 0.05),
                    logoRatio:
                        (Math.max(logoLuminance, second) + 0.05) /
                        (Math.min(logoLuminance, second) + 0.05),
                };
            },
            {
                panelClasses: panelClasses!,
                overlayClasses: overlayClasses!,
                brandClasses: brandClasses!,
                logoClasses: logoClasses!,
            },
        );

        expect(colors.foreground, colorScheme).toBe('rgb(255, 255, 255)');
        expect(colors.logoColor, colorScheme).toBe('rgb(255, 255, 255)');
        expect(colors.ratio, colorScheme).toBeGreaterThanOrEqual(4.5);
        expect(colors.logoRatio, colorScheme).toBeGreaterThanOrEqual(4.5);
    }
});

test('navegación pública expone las páginas principales', async ({ page }) => {
    await page.goto('/');
    await expect(
        page
            .getByRole('link', { name: 'Te Pinta, inicio', exact: true })
            .locator('img'),
    ).toHaveAttribute('src', '/images/brand/logo-completo-blanco.png');
    await expect(
        page.getByRole('heading', {
            name: 'Te pinta una empanada.',
            exact: true,
        }),
    ).toBeVisible();

    await page
        .getByRole('navigation', { name: 'Navegación pública' })
        .getByRole('link', { name: 'Menú', exact: true })
        .click();
    await expect(page).toHaveURL(/\/menu$/);
    await expect(
        page.getByRole('heading', { name: 'Nuestro menú.', exact: true }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Mi carrito', exact: true }).click();
    await expect(page).toHaveURL(/\/cart$/);
    await expect(
        page.getByRole('heading', { name: 'Tu carrito.', exact: true }),
    ).toBeVisible();
});

test('admin redirige visitantes al inicio de sesión', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/login$/);
    await expect(
        page.getByRole('heading', { name: 'Administración de Te Pinta' }),
    ).toBeVisible();
    await expect(
        page
            .getByRole('link', { name: 'Ir al inicio de Te Pinta' })
            .locator('[style*="mask-image"]'),
    ).toHaveCSS('mask-image', /te-pinta-mark\.svg/);
});
