import { expect, test } from '@playwright/test';

const destinations = [
    { label: 'Inicio', path: '/admin', expectedPath: '/admin' },
    {
        label: 'Dashboard',
        path: '/admin/dashboard',
        expectedPath: '/admin/dashboard',
    },
    { label: 'Pedidos', path: '/admin/orders', expectedPath: '/admin/orders' },
    {
        label: 'Clientes',
        path: '/admin/customers',
        expectedPath: '/admin/customers',
    },
    {
        label: 'Analíticas',
        path: '/admin/analytics',
        expectedPath: '/admin/analytics',
    },
    {
        label: 'Gestión',
        path: '/admin/inventory',
        expectedPath: '/admin/inventory',
    },
    {
        label: 'Configuración',
        path: '/admin/settings',
        expectedPath: '/admin/settings/products',
    },
    {
        label: 'Configuración',
        path: '/admin/settings/products',
        expectedPath: '/admin/settings/products',
    },
] as const;

test('global admin navigation exposes its destinations and active section', async ({
    page,
}) => {
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/admin$/);

    const mobile = (page.viewportSize()?.width ?? 0) < 768;

    for (const destination of destinations) {
        await page.goto(destination.path);
        expect(new URL(page.url()).pathname).toBe(destination.expectedPath);

        if (mobile) {
            const navigation = page.getByRole('navigation', {
                name: 'Navegación principal',
            });
            const primary = ['Inicio', 'Pedidos', 'Clientes'].includes(
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
            name: 'Administration navigation',
        });

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
