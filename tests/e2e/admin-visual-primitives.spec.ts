import type { Locator } from '@playwright/test';
import { adminPrimitivesMarkup } from './support/admin-primitives';
import { expect, test } from './support/test';

test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
});

async function colors(control: Locator) {
    return control.evaluate((el) => {
        const style = getComputedStyle(el);
        const luminance = (color: string) => {
            const values = color
                .match(/[\d.]+/g)!
                .slice(0, 3)
                .map(Number)
                .map((v) => {
                    const c = v / 255;

                    return c <= 0.04045
                        ? c / 12.92
                        : ((c + 0.055) / 1.055) ** 2.4;
                });

            return values.reduce(
                (sum, value, i) => sum + value * [0.2126, 0.7152, 0.0722][i],
                0,
            );
        };
        const [dark, light] = [
            luminance(style.color),
            luminance(style.backgroundColor),
        ].sort((a, b) => a - b);

        return {
            foreground: style.color,
            background: style.backgroundColor,
            contrast: (light + 0.05) / (dark + 0.05),
            height: el.getBoundingClientRect().height,
        };
    });
}

for (const [route, title] of [
    ['/admin/management', 'Gestión'],
    ['/admin/inventory', 'Inventario físico'],
]) {
    test(`${title} shares the shell, title, gutters and responsive header`, async ({
        page,
    }, info) => {
        const widths =
            info.project.name === 'mobile-chromium'
                ? [412]
                : [320, 360, 390, 393, 1280, 1920];

        for (const width of widths) {
            await page.setViewportSize({ width, height: 915 });
            await page.goto(route);
            const shell = page.getByRole('region', {
                name: title,
                exact: true,
            });
            await expect(shell).toBeVisible();
            const heading = shell.getByRole('heading', {
                level: 1,
                name: title,
                exact: true,
            });
            await expect(page.getByRole('heading', { level: 1 })).toHaveCount(
                1,
            );
            const geometry = await shell.evaluate((el) => {
                const main = el.closest('main')!;
                const style = getComputedStyle(main);
                const box = main.getBoundingClientRect();
                const rect = el.getBoundingClientRect();
                const h1 = el.querySelector('h1')!;
                const text = getComputedStyle(h1);

                return {
                    x: rect.x,
                    width: rect.width,
                    padding: parseFloat(style.paddingLeft),
                    available:
                        box.width -
                        parseFloat(style.paddingLeft) -
                        parseFloat(style.paddingRight),
                    start: box.x + parseFloat(style.paddingLeft),
                    titleX: h1.getBoundingClientRect().x,
                    font: text.fontFamily,
                    size: text.fontSize,
                    overflow: document.documentElement.scrollWidth - innerWidth,
                };
            });
            const expected = Math.min(1280, geometry.available);
            expect(geometry.width).toBeCloseTo(expected, 0);
            expect(geometry.x).toBeCloseTo(
                geometry.start + (geometry.available - expected) / 2,
                0,
            );
            expect(geometry.titleX).toBeCloseTo(geometry.x, 0);
            expect(geometry.padding).toBe(width < 640 ? 20 : 24);
            expect(geometry.font).toContain('Barlow');
            expect(geometry.size).toBe(width < 640 ? '24px' : '28px');
            expect(geometry.overflow).toBeLessThanOrEqual(1);
            await expect(heading).toBeVisible();
            const crumbs = shell.getByRole('navigation', {
                name: 'migas de pan',
            });
            await expect(
                crumbs.getByRole('link', {
                    name: 'Administración',
                    exact: true,
                }),
            ).toHaveAttribute('href', '/admin');
            const links = shell
                .getByRole('navigation', { name: /Secciones de/ })
                .getByRole('link');

            for (const link of await links.all()) {
                expect(
                    (await link.boundingBox())!.height,
                ).toBeGreaterThanOrEqual(44);
            }

            if (
                width === 1280 ||
                width === 393 ||
                info.project.name === 'mobile-chromium'
            ) {
                await page.screenshot({
                    path: `storage/app/aux1-${title}-${width}-${info.project.name}.png`,
                    fullPage: true,
                });
            }
        }

        const destinations = await page
            .getByRole('region', { name: title, exact: true })
            .getByRole('link')
            .evaluateAll((links) =>
                links
                    .map((link) => link.getAttribute('href'))
                    .filter((href): href is string => !!href),
            );

        for (const href of new Set(destinations)) {
            expect((await page.request.get(href)).status()).toBe(200);
        }
    });
}

test('section navigation follows URL, preserves context, history and keyboard focus', async ({
    page,
}) => {
    await page.goto('/admin/management?period=30_days&type=expense');
    const nav = page.getByRole('navigation', { name: 'Secciones de Gestión' });
    await expect(
        nav.getByRole('link', { name: 'Movimientos', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
    const wallets = nav.getByRole('link', { name: 'Billeteras', exact: true });
    await wallets.focus();
    await page.keyboard.press('Tab');
    await page.keyboard.press('Shift+Tab');
    expect(
        await wallets.evaluate((el) => getComputedStyle(el).outlineStyle),
    ).toBe('solid');
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(
        (url) =>
            url.searchParams.get('section') === 'wallets' &&
            url.searchParams.get('type') === 'expense' &&
            url.searchParams.get('period') === '30_days',
    );
    await page.reload();
    await expect(wallets).toHaveAttribute('aria-current', 'page');
    await page.goBack();
    await expect(
        nav.getByRole('link', { name: 'Movimientos', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
    await page.goForward();
    await expect(wallets).toHaveAttribute('aria-current', 'page');
    expect((await colors(wallets)).background).not.toBe('rgb(184, 79, 63)');
    await page
        .getByRole('button', { name: 'Nueva billetera', exact: true })
        .click();
    await expect(
        page.getByRole('dialog', { name: 'Nueva billetera', exact: true }),
    ).toBeVisible();
    await page.keyboard.press('Escape');
});

test('header actions keep Inventory operable and primary contrast accessible', async ({
    page,
}, info) => {
    await page.goto('/admin/inventory');
    await page
        .getByRole('button', { name: 'Nueva categoría', exact: true })
        .click();
    const dialog = page.getByRole('dialog', {
        name: 'Nueva categoría',
        exact: true,
    });
    await dialog
        .getByLabel('Nombre', { exact: true })
        .fill(`AUX1 ${info.project.name}`);
    await dialog.getByRole('button', { name: 'Guardar categoría' }).click();
    await expect(dialog).toBeHidden();
    const primary = page.getByRole('button', {
        name: 'Nuevo insumo',
        exact: true,
    });
    const style = await colors(primary);
    expect(style.background).toBe('rgb(184, 79, 63)');
    expect(style.foreground).toBe('rgb(255, 255, 255)');
    expect(style.contrast).toBeGreaterThanOrEqual(4.5);
    expect(style.height).toBeGreaterThanOrEqual(44);
    await primary.click();
    await expect(
        page.getByRole('dialog', { name: 'Nuevo insumo', exact: true }),
    ).toBeVisible();
    await page.keyboard.press('Escape');

    if (info.project.name === 'mobile-chromium') {
        await page.getByRole('button', { name: 'Abrir más opciones' }).click();
        await expect(
            page.getByRole('dialog', { name: 'Más opciones' }),
        ).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(
            page.getByRole('dialog', { name: 'Más opciones' }),
        ).toBeHidden();
    } else {
        await expect(
            page.getByRole('navigation', {
                name: 'Navegación de administración',
            }),
        ).toBeVisible();
    }
});

test('real primitives compose panels, forms, empty states and scoped button variants', async ({
    page,
}) => {
    await page.goto('/admin/inventory');
    const styles = await page
        .locator('link[rel=stylesheet], head > style')
        .evaluateAll((links) => links.map((link) => link.outerHTML).join(''));
    await page.setContent(`${styles}${await adminPrimitivesMarkup()}`);
    await page.evaluate(() => document.fonts.ready);
    await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
    await expect(
        page.getByRole('heading', { level: 2, name: 'Sin producciones' }),
    ).toBeVisible();
    await expect(page.getByRole('status')).toContainText(
        'Necesitas una receta vigente',
    );
    await expect(
        page.getByRole('link', { name: 'Ver recetas', exact: true }),
    ).toHaveAttribute('href', '/admin/inventory/recipes');
    await expect(page.getByRole('table')).toContainText('$ 12.500');
    expect(
        (await page
            .getByRole('form', { name: 'Formulario de ejemplo' })
            .boundingBox())!.width,
    ).toBeLessThanOrEqual(768);
    expect(
        (await page.getByLabel('Nombre', { exact: true }).boundingBox())!
            .height,
    ).toBeGreaterThanOrEqual(44);

    for (const label of [
        'Guardar',
        'Secundario',
        'Contorno',
        'Navegación',
        'Eliminar',
    ]) {
        expect(
            (
                await colors(
                    page.getByRole('button', { name: label, exact: true }),
                )
            ).height,
        ).toBeGreaterThanOrEqual(44);
    }

    await expect(
        page.getByRole('button', { name: 'Guardando…' }),
    ).toBeDisabled();
    await expect(
        page.getByRole('button', { name: 'Guardando…' }),
    ).toHaveAttribute('aria-busy', 'true');
    expect(
        (await colors(page.getByRole('button', { name: 'Fuera de alcance' })))
            .height,
    ).toBe(36);
    expect(
        (await colors(page.getByRole('button', { name: 'Fuera de alcance' })))
            .foreground,
    ).toBe('rgb(255, 255, 255)');
    expect(
        (
            await colors(
                page.getByRole('button', { name: 'Secundario', exact: true }),
            )
        ).contrast,
    ).toBeGreaterThanOrEqual(4.5);
    expect(
        (
            await colors(
                page.getByRole('button', { name: 'Eliminar', exact: true }),
            )
        ).contrast,
    ).toBeGreaterThanOrEqual(4.5);

    // Resize the available content independently of the viewport/sidebar host.
    for (const width of [320, 1280, 1920]) {
        await page.setViewportSize({ width, height: 915 });

        for (const rail of width < 640 ? [0] : [0, 104]) {
            await page
                .locator('.admin-shell')
                .evaluate(
                    (el, value) =>
                        (el as HTMLElement).style.setProperty(
                            '--rail-width',
                            `${value}px`,
                        ),
                    rail,
                );
            const layout = await page
                .getByRole('region', { name: 'Primitivas administrativas' })
                .evaluate((el) => {
                    const panel = el.querySelector('[data-slot="card"]')!;
                    const content = panel.querySelector(
                        '[data-slot="card-content"]',
                    )!;
                    const shell = el.getBoundingClientRect();

                    return {
                        width: shell.width,
                        padding: getComputedStyle(content).paddingLeft,
                        radius: getComputedStyle(panel).borderRadius,
                        overflow:
                            document.documentElement.scrollWidth - innerWidth,
                    };
                });
            expect(layout.width).toBe(
                Math.min(1280, width - rail - (width < 640 ? 40 : 48)),
            );
            expect(layout.padding).toBe(width < 640 ? '16px' : '24px');
            expect(layout.radius).toBe('12px');
            expect(layout.overflow).toBeLessThanOrEqual(1);
        }
    }

    await page.getByRole('link', { name: 'Ver recetas', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/inventory\/recipes$/);
});

test('desktop sidebar keeps Marketing and later destinations reachable on short screens', async ({
    page,
}, info) => {
    test.skip(info.project.name !== 'chromium');

    for (const height of [720, 480]) {
        await page.setViewportSize({ width: 1280, height });
        await page.goto('/admin/dashboard');
        const navigation = page.getByRole('navigation', {
            name: 'Navegación de administración',
            exact: true,
        });
        const marketing = navigation.getByRole('link', {
            name: 'Marketing',
            exact: true,
        });
        await marketing.click({ timeout: 5_000 });
        await expect(page).toHaveURL(/\/admin\/marketing$/);
        await expect(marketing).toBeInViewport();
        await expect(marketing).toHaveAttribute('aria-current', 'page');
        await expect(
            page.getByRole('link', { name: 'Te Pinta POS', exact: true }),
        ).toBeInViewport();
        await expect(
            page.getByRole('button', { name: 'Cerrar sesión', exact: true }),
        ).toBeInViewport();

        const settings = navigation.getByRole('link', {
            name: 'Configuración',
            exact: true,
        });
        await settings.focus();
        await expect(settings).toBeFocused();
        await expect(settings).toBeInViewport();
        await settings.press('Shift+Tab');
        const management = navigation.getByRole('link', {
            name: 'Gestión',
            exact: true,
        });
        await expect(management).toBeFocused();
        await expect(management).toBeInViewport();

        await navigation
            .getByRole('link', { name: 'Vender', exact: true })
            .click();
        await expect(page).toHaveURL(/\/admin$/);
        await expect(
            navigation.getByRole('link', { name: 'Vender', exact: true }),
        ).toBeInViewport();
        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            ),
        ).toBeLessThanOrEqual(1);
    }
});
