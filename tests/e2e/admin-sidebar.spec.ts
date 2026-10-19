import { expect, test } from './support/test';

const destinations = [
    { label: 'Vender', path: '/admin', expectedPath: '/admin' },
    { label: 'Pedidos', path: '/admin/orders', expectedPath: '/admin/orders' },
    {
        label: 'Pedidos',
        path: '/admin/orders/create',
        expectedPath: '/admin/orders/create',
    },
    {
        label: 'Clientes',
        path: '/admin/customers',
        expectedPath: '/admin/customers',
    },
    {
        label: 'Panel',
        path: '/admin/dashboard',
        expectedPath: '/admin/dashboard',
    },
    {
        label: 'Productos',
        path: '/admin/settings',
        expectedPath: '/admin/settings/products',
    },
    {
        label: 'Marketing',
        path: '/admin/marketing',
        expectedPath: '/admin/marketing',
    },
    {
        label: 'Marketing',
        path: '/admin/marketing/instagram',
        expectedPath: '/admin/marketing/instagram',
    },
    {
        label: 'Inventario',
        path: '/admin/inventory/recipes',
        expectedPath: '/admin/inventory/recipes',
    },
    {
        label: 'Gestión',
        path: '/admin/management/audit',
        expectedPath: '/admin/management/audit',
    },
    {
        label: 'Configuración',
        path: '/admin/settings/general',
        expectedPath: '/admin/settings/general',
    },
    {
        label: 'Configuración',
        path: '/admin/restaurant/floor',
        expectedPath: '/admin/restaurant/floor',
    },
    {
        label: 'Configuración',
        path: '/admin/users',
        expectedPath: '/admin/users',
    },
] as const;

const groups = [
    'Operación',
    'Resumen',
    'Catálogo',
    'Stock y costos',
    'Finanzas',
    'Configuración',
];

test('navegación administrativa global expone destinos y sección activa', async ({
    page,
}) => {
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);

    const mobile = (page.viewportSize()?.width ?? 0) < 768;

    if (!mobile) {
        await expect(
            page
                .getByRole('link', { name: 'Te Pinta POS' })
                .locator('[style*="mask-image"]'),
        ).toHaveCSS('mask-image', /te-pinta-mark\.svg/);
    }

    for (const destination of destinations) {
        await page.goto(destination.path);
        expect(new URL(page.url()).pathname).toBe(destination.expectedPath);

        if (mobile) {
            const navigation = page.getByRole('navigation', {
                name: 'Navegación principal',
            });
            const primary = ['Vender', 'Pedidos', 'Panel'].includes(
                destination.label,
            );

            if (primary) {
                await expect(
                    navigation.getByRole('link', {
                        name: destination.label,
                    }),
                ).toHaveAttribute('aria-current', 'page');
            } else {
                const moreButton = navigation.getByRole('button', {
                    name: 'Abrir más opciones',
                });
                const box = await moreButton.boundingBox();
                expect(box).not.toBeNull();
                await page.touchscreen.tap(
                    box!.x + box!.width / 2,
                    box!.y + box!.height / 2,
                );
                await expect(
                    page
                        .getByRole('dialog')
                        .getByRole('link', { name: destination.label }),
                ).toHaveAttribute('aria-current', 'page');
            }

            continue;
        }

        const navigation = page.getByRole('navigation', {
            name: 'Navegación de administración',
        });

        await expect(navigation.getByRole('link')).toHaveCount(9);
        await expect(
            navigation.getByRole('link', { name: 'Analíticas' }),
        ).toHaveCount(0);

        await expect(navigation.locator('p')).toHaveText(groups);

        await expect(
            navigation.getByRole('link', { name: destination.label }),
        ).toHaveAttribute('aria-current', 'page');

        for (const other of destinations) {
            if (other.label !== destination.label) {
                await expect(
                    navigation.getByRole('link', { name: other.label }),
                ).not.toHaveAttribute('aria-current', 'page');
            }
        }
    }
});
