import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import type { ManagementPageProps } from '@/features/management/types';
import { formatMoney } from '@/lib/format';
import { expect, test } from './support/test';

test.beforeEach(() => {
    execFileSync(
        'php',
        [
            'artisan',
            'db:seed',
            '--class=Tests\\Support\\ManagementOverviewSeeder',
            '--force',
        ],
        {
            env: {
                ...process.env,
                APP_ENV: 'testing',
                APP_DEBUG: 'false',
                DB_DATABASE: process.env.PLAYWRIGHT_DB_DATABASE,
                DB_URL: '',
            },
            stdio: 'pipe',
        },
    );
});

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

async function model(page: Page): Promise<ManagementPageProps> {
    const version = JSON.parse(
        (await page.locator('script[data-page="app"]').textContent()) ?? '{}',
    ).version;
    const response = await page.request.get(page.url(), {
        headers: {
            'X-Inertia': 'true',
            ...(version ? { 'X-Inertia-Version': version } : {}),
            Accept: 'text/html',
        },
    });
    expect(response.status()).toBe(200);

    return (await response.json()).props;
}

function shiftDay(day: string, offset: number) {
    const date = new Date(`${day}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + offset);

    return date.toISOString().slice(0, 10);
}

async function geometry(page: Page, width: number) {
    const shell = page.getByRole('region', { name: 'Gestión', exact: true });
    const actual = await shell.evaluate((el) => {
        const box = el.getBoundingClientRect();
        const main = el.closest('main')!;
        const style = getComputedStyle(main);
        const h1 = el.querySelector('h1')!;

        return {
            width: box.width,
            titleX: h1.getBoundingClientRect().x,
            x: box.x,
            available:
                main.clientWidth -
                parseFloat(style.paddingLeft) -
                parseFloat(style.paddingRight),
            gutter: style.paddingLeft,
            font: getComputedStyle(h1).fontFamily,
            size: getComputedStyle(h1).fontSize,
            overflow: document.documentElement.scrollWidth - innerWidth,
        };
    });
    expect(actual.overflow).toBe(0);
    expect(actual.width).toBeCloseTo(Math.min(1280, actual.available), 0);
    expect(actual.titleX).toBe(actual.x);
    expect(actual.gutter).toBe(width < 640 ? '20px' : '24px');
    expect(actual.size).toBe(width < 640 ? '24px' : '28px');
    expect(actual.font).toContain('Barlow');
    await expect(page.getByRole('heading', { level: 1 })).toHaveCount(1);
    expect(
        await page
            .getByRole('button', { name: 'Aplicar período', exact: true })
            .evaluate((el) => getComputedStyle(el).color),
    ).toBe('rgb(255, 255, 255)');
    const controls = shell
        .locator('.admin-ui')
        .getByRole('button')
        .or(shell.locator('.admin-ui').getByRole('link'))
        .or(shell.locator('.admin-ui select'))
        .or(shell.locator('.admin-ui input'));

    for (const control of await controls.all()) {
        if (await control.isVisible()) {
            expect(
                (await control.boundingBox())!.height,
            ).toBeGreaterThanOrEqual(44);
        }
    }

    if (width < 768) {
        const audit = page.getByRole('link', {
            name: 'Ver auditoría del período',
            exact: true,
        });
        await audit.scrollIntoViewIfNeeded();
        const mobileNavTop = await page
            .getByRole('button', { name: 'Abrir más opciones' })
            .evaluate((el) => el.closest('nav')!.getBoundingClientRect().top);
        expect(
            (await audit.boundingBox())!.y +
                (await audit.boundingBox())!.height,
        ).toBeLessThanOrEqual(mobileNavTop);
    }

    return actual;
}

test('Panorama prioritizes three KPIs without losing expenses, wallet balances or reconciliation', async ({
    page,
}, info) => {
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    const widths =
        info.project.name === 'mobile-chromium'
            ? [412]
            : [320, 360, 390, 393, 1280, 1920];

    for (const width of widths) {
        await page.setViewportSize({ width, height: 915 });
        await page.goto('/admin/management?period=30_days');
        const props = await model(page);
        const kpis = page.getByRole('region', {
            name: 'Indicadores principales',
            exact: true,
        });
        await expect(kpis.getByRole('group')).toHaveCount(3);

        for (const [name, value] of [
            ['Saldo actual', props.summary.total_balance],
            ['Ingresos del período', props.summary.period_income],
            ['Resultado operativo', props.summary.period_operating_result],
        ] as const) {
            await expect(
                kpis.getByRole('group', { name, exact: true }),
            ).toContainText(formatMoney(value));
        }

        const outflows = page.getByRole('region', {
            name: 'Egresos del período',
            exact: true,
        });

        for (const value of [
            props.summary.period_purchases,
            props.summary.period_expenses + (props.summary.period_expense ?? 0),
            props.summary.period_personal_withdrawal,
            props.summary.period_purchase_shipping,
        ]) {
            await expect(outflows).toContainText(formatMoney(value));
        }

        await expect(outflows).toContainText('Egresos manuales');
        const internal = page.getByRole('region', {
            name: 'Saldos del período y movimientos internos',
            exact: true,
        });

        for (const value of [
            props.summary.period_opening_balance,
            props.summary.period_closing_balance,
            props.summary.period_distribution,
            props.summary.period_other_movements,
        ]) {
            await expect(internal).toContainText(formatMoney(value));
        }

        const wallets = page.getByRole('region', {
            name: 'Saldos actuales por billetera',
            exact: true,
        });

        for (const wallet of props.wallets) {
            await expect(
                wallets.getByRole('listitem').filter({ hasText: wallet.name }),
            ).toContainText(formatMoney(wallet.balance));
        }

        await expect(wallets).toContainText('Archivada');
        await expect(
            page.getByRole('region', {
                name: 'Conciliación del período',
                exact: true,
            }),
        ).toContainText(formatMoney(props.summary.reconciliation_difference));
        await geometry(page, width);
    }

    const before = (await model(page)).summary;
    await page.goto(
        '/admin/management?section=panorama&period=30_days&type=expense',
    );
    expect((await model(page)).summary).toEqual(before);
    expect(errors).toEqual([]);
});

test('header operations are contextual and URL navigation preserves the reporting context', async ({
    page,
}) => {
    await page.goto('/admin/management?period=30_days');
    await expect(
        page.getByRole('button', { name: 'Nueva billetera', exact: true }),
    ).toHaveCount(0);
    const nav = page.getByRole('navigation', { name: 'Secciones de Gestión' });
    await expect(nav.getByRole('link')).toHaveText([
        'Panorama',
        'Movimientos',
        'Billeteras',
        'Compras',
        'Auditoría',
    ]);
    await expect(
        nav.getByRole('link', { name: 'Compras', exact: true }),
    ).toHaveAttribute('href', '/admin/management/purchases');
    await expect(
        nav.getByRole('link', { name: 'Auditoría', exact: true }),
    ).toHaveAttribute('href', '/admin/management/audit');
    const register = page
        .locator('header')
        .filter({
            has: page.getByRole('heading', { name: 'Gestión', level: 1 }),
        })
        .getByRole('link', { name: 'Registrar operación', exact: true });
    await expect(register).toHaveAttribute(
        'href',
        /period=30_days.*section=operations/,
    );
    await nav.getByRole('link', { name: 'Billeteras', exact: true }).click();
    await expect(page).toHaveURL(
        (url) =>
            url.searchParams.get('section') === 'wallets' &&
            url.searchParams.get('period') === '30_days',
    );
    const create = page.getByRole('button', {
        name: 'Nueva billetera',
        exact: true,
    });
    await expect(create).toHaveCount(1);
    await create.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog')).toBeHidden();
    // Close-focus restoration already fails on 426001c; track its separate AUX-2B fix.
    // This slice verifies the relocated trigger, keyboard launch and existing close behavior.
    await nav.getByRole('link', { name: 'Panorama', exact: true }).click();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('section') === 'panorama',
    );
    await page.goBack();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('section') === 'wallets',
    );
    await expect(create).toBeVisible();
    await page.goForward();
    await expect(
        nav.getByRole('link', { name: 'Panorama', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
    await page.reload();
    await expect(create).toHaveCount(0);
    await expect(
        page
            .getByRole('navigation', { name: 'migas de pan' })
            .getByRole('link', { name: 'Panel', exact: true }),
    ).toHaveAttribute('href', '/admin/dashboard');
});

test('a period without activity keeps the current balance distinct and offers valid next steps', async ({
    page,
}, info) => {
    await page.goto('/admin/management?period=30_days');
    const initial = await model(page);
    const from = shiftDay(initial.business_today, -61),
        to = shiftDay(initial.business_today, -60);
    await page.getByLabel('Período', { exact: true }).selectOption('custom');
    await page
        .getByTestId('management-summary-group')
        .getByLabel('Desde', { exact: true })
        .fill(from);
    await page
        .getByTestId('management-summary-group')
        .getByLabel('Hasta', { exact: true })
        .fill(to);
    await page
        .getByRole('button', { name: 'Aplicar período', exact: true })
        .click();
    await expect(page).toHaveURL(
        (url) =>
            url.searchParams.get('from') === from &&
            url.searchParams.get('to') === to &&
            url.searchParams.get('section') === 'panorama',
    );
    const props = await model(page);
    expect(props.summary.period_income).toBe(0);
    expect(props.summary.period_operating_result).toBe(0);
    expect(props.summary.total_balance).toBe(initial.summary.total_balance);

    for (const input of await page
        .getByTestId('management-summary-group')
        .locator('input[type="date"]')
        .all()) {
        await expect(input).toHaveCSS('color-scheme', 'dark');
    }

    const status = page
        .getByRole('status')
        .filter({ hasText: 'Sin actividad neta en este período' });
    await expect(status).toContainText('valores netos');
    await expect(
        status.getByRole('link', { name: 'Registrar operación', exact: true }),
    ).toHaveAttribute('href', /section=operations/);
    await expect(
        status.getByRole('link', { name: 'Ver movimientos', exact: true }),
    ).toHaveAttribute('href', /section=movements/);

    for (const width of info.project.name === 'mobile-chromium'
        ? [412]
        : [320, 360, 390, 393, 1280, 1920]) {
        await page.setViewportSize({ width, height: 915 });
        await geometry(page, width);
    }
});

test('net-zero purchase and reversal are not described as missing financial history', async ({
    page,
}) => {
    await page.goto('/admin/management');
    const yesterday = shiftDay((await model(page)).business_today, -1);
    await page.goto(
        `/admin/management?period=custom&from=${yesterday}&to=${yesterday}`,
    );
    const props = await model(page);
    expect(props.summary.period_purchases).toBe(0);
    expect(props.transactions.total).toBeGreaterThan(0);
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Sin actividad neta en este período' }),
    ).toContainText('reversiones');
    await expect(
        page.getByText('Todavía no registraste operaciones.', { exact: true }),
    ).toHaveCount(0);
});

test('Compras and Auditoría are Gestión sections with the same tabs', async ({
    page,
}) => {
    const nav = page.getByRole('navigation', { name: 'Secciones de Gestión' });

    await page.goto('/admin/management/purchases');
    await expect(
        nav.getByRole('link', { name: 'Compras', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
    await page.goto('/admin/management/purchases/create');
    await expect(
        nav.getByRole('link', { name: 'Compras', exact: true }),
    ).toHaveAttribute('aria-current', 'page');

    await nav.getByRole('link', { name: 'Auditoría', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/management\/audit$/);
    await expect(
        page.getByRole('heading', { name: 'Auditoría', level: 1 }),
    ).toBeVisible();
    await expect(
        nav.getByRole('link', { name: 'Auditoría', exact: true }),
    ).toHaveAttribute('aria-current', 'page');

    await nav.getByRole('link', { name: 'Movimientos', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/management\?section=movements$/);
    await expect(
        nav.getByRole('link', { name: 'Movimientos', exact: true }),
    ).toHaveAttribute('aria-current', 'page');
});
