import { expect, test } from '@playwright/test';
import type { Page, TestInfo } from '@playwright/test';
import type { MarketingPageProps } from '@/features/marketing/types';

const businessTimezone = 'America/Argentina/Buenos_Aires';

test.describe.configure({ mode: 'serial' });

async function logIn(page: Page) {
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

async function waitForStableCapture(page: Page) {
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(250);
}

function screenshotFor(testInfo: TestInfo, desktop: string, mobile: string) {
    return testInfo.project.name === 'mobile-chromium' ? mobile : desktop;
}

function businessLocal(daysFromNow: number): string {
    const instant = new Date(Date.now() + daysFromNow * 86_400_000);
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: businessTimezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    })
        .formatToParts(instant)
        .reduce<Record<string, string>>((values, part) => {
            values[part.type] = part.value;

            return values;
        }, {});

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

async function selectProduct(page: Page, product: string) {
    await page.getByRole('combobox').click();
    await page.getByRole('option', { name: product, exact: true }).click();
}

test('administración muestra recomendación, preview, programación y responsive', async ({
    page,
}, testInfo) => {
    await logIn(page);
    await page.goto('/admin/marketing');

    await expect(
        page.getByRole('heading', { name: 'Marketing', exact: true }),
    ).toBeVisible();
    const current = page.getByTestId('marketing-current');
    await expect(current).toContainText('Salteña');
    await expect(current).toContainText('Nuestra recomendación de hoy');
    await expect(current).toContainText('Activa');

    const preview = page.getByTestId('marketing-preview');
    await expect(preview.getByTestId('featured-product-card')).toContainText(
        'Salteña',
    );
    await expect(page.getByTestId('marketing-schedule')).toContainText(
        'Caprese',
    );
    await expect(page.getByText('Descuentos', { exact: true })).toBeVisible();
    await expect(
        page.getByText('Códigos promocionales', { exact: true }),
    ).toBeVisible();

    if (testInfo.project.name === 'mobile-chromium') {
        expect(
            await page.evaluate(
                () =>
                    document.documentElement.scrollWidth -
                    document.documentElement.clientWidth,
            ),
        ).toBeLessThanOrEqual(1);
    }

    await waitForStableCapture(page);
    await page.screenshot({
        path: screenshotFor(
            testInfo,
            'storage/app/marketing-admin-desktop.png',
            'storage/app/marketing-admin-mobile.png',
        ),
        fullPage: true,
        animations: 'disabled',
    });
});

test('administración presenta un empty state realista sin mutar fixtures', async ({
    page,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium');

    await logIn(page);
    await page.goto('/admin/dashboard');
    await page.route('**/admin/marketing', async (route) => {
        if (!route.request().headers()['x-inertia']) {
            await route.continue();

            return;
        }

        const response = await route.fetch();
        const payload = (await response.json()) as {
            props: MarketingPageProps;
        };
        payload.props.current = null;
        payload.props.upcoming = [];
        payload.props.history = [];

        await route.fulfill({ response, json: payload });
    });

    await page.getByRole('link', { name: 'Marketing', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/marketing$/);
    await expect(page.getByTestId('marketing-current')).toContainText(
        'No hay una recomendación activa',
    );
    await expect(
        page.getByRole('button', { name: 'Crear recomendación' }),
    ).toBeVisible();
});

test('administración crea, previsualiza, edita y desactiva una programación futura', async ({
    page,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium');

    await logIn(page);
    await page.goto('/admin/marketing');
    await page.getByRole('button', { name: 'Nueva recomendación' }).click();
    await selectProduct(page, 'Humita Tucumana');
    await page.getByLabel('Título destacado').fill('Elegida para esta semana');
    await page
        .getByLabel('Texto corto')
        .fill('Una recomendación suave y sabrosa.');
    const editorDialog = page.getByRole('dialog');
    await editorDialog.getByLabel('Inicio').fill(businessLocal(4));
    await editorDialog.getByLabel('Fin').fill(businessLocal(5));

    const editorPreview = page.getByTestId('marketing-editor-preview');
    await expect(editorPreview).toContainText('Humita Tucumana');
    await expect(editorPreview).toContainText('Elegida para esta semana');
    await expect(editorPreview).toContainText(
        'Una recomendación suave y sabrosa.',
    );

    await page.getByRole('button', { name: 'Guardar recomendación' }).click();
    await expect(
        page.getByRole('dialog', { name: 'Nueva recomendación' }),
    ).toHaveCount(0);

    let scheduleRow = page
        .getByTestId('marketing-schedule')
        .getByRole('listitem')
        .filter({ hasText: 'Humita Tucumana' });
    await expect(scheduleRow).toContainText('Programada');
    await scheduleRow
        .getByRole('button', {
            name: 'Editar recomendación de Humita Tucumana',
        })
        .click();
    await page.getByLabel('Título destacado').fill('Elegida y actualizada');
    await page.getByRole('button', { name: 'Guardar recomendación' }).click();

    scheduleRow = page
        .getByTestId('marketing-schedule')
        .getByRole('listitem')
        .filter({ hasText: 'Humita Tucumana' });
    await scheduleRow
        .getByRole('button', {
            name: 'Editar recomendación de Humita Tucumana',
        })
        .click();
    await expect(page.getByLabel('Título destacado')).toHaveValue(
        'Elegida y actualizada',
    );
    await page.getByRole('button', { name: 'Cancelar' }).click();

    await scheduleRow
        .getByRole('button', {
            name: 'Desactivar recomendación de Humita Tucumana',
        })
        .click();
    const disableDialog = page.getByRole('dialog', {
        name: 'Desactivar recomendación',
    });
    await disableDialog.getByRole('button', { name: 'Desactivar' }).click();
    await expect(page.getByTestId('marketing-history')).toContainText(
        'Humita Tucumana',
    );
});

test('administración rechaza una programación superpuesta', async ({
    page,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium');

    await logIn(page);
    await page.goto('/admin/marketing');
    await page.getByRole('button', { name: 'Nueva recomendación' }).click();
    await selectProduct(page, 'Criolla Salada');
    const editorDialog = page.getByRole('dialog');
    await editorDialog.getByLabel('Inicio').fill(businessLocal(-0.25));
    await editorDialog.getByLabel('Fin').fill(businessLocal(0.25));
    await page.getByRole('button', { name: 'Guardar recomendación' }).click();

    await expect(
        page.getByRole('alert').filter({
            hasText:
                'Ya existe una recomendación programada para parte de este período.',
        }),
    ).toBeVisible();
});

test('Inicio público muestra la recomendación y reutiliza el carrito', async ({
    page,
}, testInfo) => {
    await page.goto('/');

    const featured = page.getByTestId('featured-product-card');
    await expect(featured).toContainText('Recomendación de la casa');
    await expect(featured).toContainText('Nuestra recomendación de hoy');
    await expect(featured).toContainText('Salteña');
    await expect(featured).toContainText('Desde · docena cocinada');
    await expect(featured).toContainText('$ 18.000');

    await waitForStableCapture(page);
    await page.screenshot({
        path: screenshotFor(
            testInfo,
            'storage/app/marketing-public-home.png',
            'storage/app/marketing-public-mobile.png',
        ),
        fullPage: true,
        animations: 'disabled',
    });

    await featured
        .getByRole('button', { name: /Agregar una unidad de Salteña/ })
        .click();
    await page.getByRole('link', { name: /Carrito/ }).click();
    await expect(page).toHaveURL(/\/cart$/);
    await expect(page.getByText('Salteña', { exact: true })).toBeVisible();
    await expect(page.getByLabel('Cantidad')).toHaveText('1');
});

test('Inicio no reserva espacio cuando el backend no expone recomendación', async ({
    page,
}) => {
    await page.goto('/menu');
    await page.route('**/', async (route) => {
        if (!route.request().headers()['x-inertia']) {
            await route.continue();

            return;
        }

        const response = await route.fetch();
        const payload = (await response.json()) as {
            props: { featuredProduct: unknown };
        };
        payload.props.featuredProduct = null;

        await route.fulfill({ response, json: payload });
    });

    await page.getByRole('link', { name: 'Inicio', exact: true }).click();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByTestId('featured-product-card')).toHaveCount(0);
    await expect(
        page.getByRole('heading', { name: 'Tu próximo pedido empieza acá.' }),
    ).toBeVisible();
});

test('Dashboard muestra el resumen compacto de Marketing', async ({
    page,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium');

    await logIn(page);
    await page.goto('/admin/dashboard');
    const summary = page.getByTestId('dashboard-marketing-summary');
    await expect(summary).toContainText('Recomendación activa');
    await expect(summary).toContainText('Salteña');
    await expect(
        summary.getByRole('link', { name: /Gestionar marketing/ }),
    ).toHaveAttribute('href', '/admin/marketing');

    await waitForStableCapture(page);
    await page.screenshot({
        path: 'storage/app/dashboard-marketing-summary.png',
        fullPage: true,
        animations: 'disabled',
    });
});
