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

test('las páginas absorbidas muestran las secciones de su módulo', async ({
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

    const modules = [
        {
            path: '/admin/inventory/recipes',
            label: 'Secciones de Inventario',
            sections: [
                'Insumos',
                'Recetas',
                'Producción',
                'Ajustes de stock',
                'Costos',
            ],
            active: 'Recetas',
        },
        {
            path: '/admin/inventory/costing-policies',
            label: 'Secciones de Inventario',
            active: 'Costos',
        },
        {
            path: '/admin/marketing/instagram',
            label: 'Secciones de Marketing',
            sections: ['Producto destacado', 'Instagram'],
            active: 'Instagram',
        },
        {
            path: '/admin/restaurant/floor',
            label: 'Secciones de Configuración',
            sections: ['General', 'Plano del salón', 'Usuarios'],
            active: 'Plano del salón',
        },
    ];

    for (const module of modules) {
        await page.goto(module.path);
        const navigation = page.getByRole('navigation', {
            name: module.label,
            exact: true,
        });

        if (module.sections) {
            await expect(navigation.getByRole('link')).toHaveText(
                module.sections,
            );
        }

        await expect(navigation.locator('[aria-current="page"]')).toHaveText(
            module.active,
        );
    }

    // Pages with one destination get no tabs.
    await page.goto('/admin/customers');
    await expect(
        page.getByRole('navigation', { name: /^Secciones de/ }),
    ).toHaveCount(0);
});

test('el escritorio ancho muestra etiquetas y grupos; debajo de 1536 px queda el riel', async ({
    page,
}, info) => {
    test.skip(info.project.name !== 'chromium');

    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);

    const sidebar = page.locator('aside.admin-sidebar');
    const navigation = page.getByRole('navigation', {
        name: 'Navegación de administración',
    });

    await page.setViewportSize({ width: 1600, height: 900 });
    await page.goto('/admin/orders');
    expect((await sidebar.boundingBox())?.width).toBe(232);
    await expect(
        navigation.getByText('Operación', { exact: true }),
    ).toBeVisible();
    await expect(
        navigation.getByRole('link', { name: 'Pedidos' }).getByText('Pedidos'),
    ).toBeVisible();

    // Below 2xl the content keeps the width it had before the labelled sidebar.
    await page.setViewportSize({ width: 1280, height: 720 });
    expect((await sidebar.boundingBox())?.width).toBe(104);
    await expect(
        navigation.getByText('Operación', { exact: true }),
    ).toBeHidden();
    await expect(
        navigation.getByRole('link', { name: 'Pedidos' }).getByText('Pedidos'),
    ).toBeHidden();
});
