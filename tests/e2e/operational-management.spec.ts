import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

test.use({ timezoneId: 'Pacific/Kiritimati' });

async function login(page: Page) {
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
}

test('Management sections are URL-authoritative across reload, history and filters', async ({
    page,
}, info) => {
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await login(page);
    await page.goto('/admin/management?period=30_days');
    const nav = page.getByRole('navigation', { name: 'Secciones de Gestión' });
    await expect(
        nav.getByRole('link', { name: 'Panorama', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
    await expect(page.getByTestId('management-summary-group')).toBeVisible();
    await nav
        .getByRole('link', { name: 'Registrar operación', exact: true })
        .click();
    await expect(page).toHaveURL(/section=operations/);
    await expect(
        page.getByRole('button', { name: 'Registrar gasto', exact: true }),
    ).toBeVisible();
    await expect(page.getByTestId('management-summary-group')).toBeHidden();
    await nav.getByRole('link', { name: 'Billeteras', exact: true }).click();
    await expect(page).toHaveURL(/section=wallets/);
    await page.reload();
    await expect(
        nav.getByRole('link', { name: 'Billeteras', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
    await page.goBack();
    await expect(
        nav.getByRole('link', { name: 'Registrar operación', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
    await page.goForward();
    await expect(page.getByTestId('management-wallets-group')).toBeVisible();
    await nav.getByRole('link', { name: 'Movimientos', exact: true }).click();
    await page.locator('#history-from').fill('2026-08-01');
    await page.locator('#history-to').fill('2026-08-31');
    await page.getByRole('button', { name: 'Filtrar', exact: true }).click();
    await expect(page).toHaveURL(
        (url) =>
            url.searchParams.get('section') === 'movements' &&
            url.searchParams.get('period') === '30_days' &&
            url.searchParams.get('history_from') === '2026-08-01',
    );
    await page.reload();
    await expect(page.locator('#history-from')).toHaveValue('2026-08-01');
    await nav.getByRole('link', { name: 'Panorama', exact: true }).click();
    await page.getByLabel('Período', { exact: true }).selectOption('today');
    await page
        .getByRole('button', { name: 'Aplicar período', exact: true })
        .click();
    await expect(page).toHaveURL(
        (url) =>
            url.searchParams.get('period') === 'today' &&
            url.searchParams.get('history_from') === '2026-08-01',
    );
    await page.goto('/admin/management?type=expense');
    await expect(
        nav.getByRole('link', { name: 'Movimientos', exact: true }),
    ).toHaveAttribute('aria-current', 'page');

    for (const url of [
        '/admin/management/purchases',
        '/admin/management/purchases/create',
        '/admin/management/audit',
    ]) {
        const response = await page.goto(url);
        expect(response?.status()).toBe(200);
    }

    await page.goto('/admin/management?section=panorama');
    await expect
        .poll(() =>
            page.evaluate(
                () => document.documentElement.scrollWidth - window.innerWidth,
            ),
        )
        .toBeLessThanOrEqual(1);
    await page.screenshot({
        path: `storage/app/aob7-panorama-${info.project.name}.png`,
        fullPage: true,
    });
    expect(errors).toEqual([]);
});

test('Management exposes one Rich Purchase entry point and retains financial operation dialogs', async ({
    page,
}) => {
    await login(page);
    await page.goto('/admin/management?section=operations');
    await expect(
        page.getByRole('link', { name: 'Registrar compra', exact: true }),
    ).toHaveAttribute('href', '/admin/management/purchases/create');
    await expect(
        page.getByRole('button', { name: 'Registrar compra', exact: true }),
    ).toHaveCount(0);
    const initial = JSON.parse(
        (await page.locator('script[data-page="app"]').textContent()) ?? '{}',
    );
    await page
        .getByRole('button', { name: 'Registrar gasto', exact: true })
        .click();
    await expect(
        page.getByRole('dialog').locator('input[type="date"]'),
    ).toHaveValue(initial.props.business_today);
    await page.locator('#outflow-type').click();
    await expect(page.getByRole('listbox')).toBeVisible();
    await expect(
        page.getByRole('option', { name: 'Compra', exact: true }),
    ).toHaveCount(0);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('listbox')).toBeHidden();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toBeHidden();
    await page
        .getByRole('button', { name: 'Nueva billetera', exact: true })
        .click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toBeHidden();
    await page
        .getByRole('button', { name: 'Movimiento manual / Más acciones' })
        .click();
    const movement = page.getByRole('dialog');
    await expect(movement).toBeVisible();
    await page.locator('#movement-type').click();
    await page
        .getByRole('option', { name: 'Ingreso manual', exact: true })
        .click();
    await page.locator('#movement-amount').fill('137');
    await expect(page.locator('#movement-effective-date')).toHaveValue(
        initial.props.business_today,
    );
    await movement
        .getByRole('button', { name: 'Registrar movimiento', exact: true })
        .click();
    await expect(movement).toBeHidden();
    await page.goto('/admin/management?section=panorama');
    const after = JSON.parse(
        (await page.locator('script[data-page="app"]').textContent()) ?? '{}',
    );
    expect(after.props.summary.period_income).toBe(
        initial.props.summary.period_income + 137,
    );
    expect(after.props.summary.total_balance).toBe(
        initial.props.summary.total_balance + 137,
    );
    await page.goto('/admin/management?section=movements');
    await page.locator('#history-type').click();
    await expect(
        page.getByRole('option', { name: 'Compra', exact: true }),
    ).toBeVisible();
});
