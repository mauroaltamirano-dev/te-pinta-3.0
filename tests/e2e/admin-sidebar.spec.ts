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

test('global admin sidebar exposes its destinations and active section', async ({
    page,
}) => {
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/admin$/);

    const navigation = page.getByRole('navigation', {
        name: 'Administration navigation',
    });

    for (const destination of destinations) {
        await page.goto(destination.path);
        expect(new URL(page.url()).pathname).toBe(destination.expectedPath);

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
