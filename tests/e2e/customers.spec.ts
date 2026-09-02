import { expect, test } from '@playwright/test';
import type { Page, TestInfo } from '@playwright/test';

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

async function createOrder(page: Page, customerName: string) {
    await page.goto('/admin/orders/create');
    await page
        .getByRole('button', { name: 'Agregar una docena de Salteña' })
        .click();
    await page.getByLabel('Nombre').fill(customerName);
    await page.getByRole('button', { name: 'Crear pedido' }).click();
    await expect(page).toHaveURL(/\/admin\/orders\/TP-\d+$/);

    return page.url().split('/').at(-1) as string;
}

function screenshotPath(testInfo: TestInfo, name: string) {
    const mobile = testInfo.project.name === 'mobile-chromium';

    return `storage/app/${name}${mobile ? '-mobile' : '-desktop'}.png`;
}

async function openCustomerFromList(page: Page, name: string) {
    if ((page.viewportSize()?.width ?? 0) < 768) {
        const customerCard = page.locator('article').filter({
            has: page.getByRole('heading', { name, exact: true }),
        });

        await expect(customerCard).toHaveCount(1);
        await customerCard.getByRole('link').click();

        return;
    }

    await page.getByRole('link', { name, exact: true }).click();
}

test.beforeEach(async ({ page }) => {
    await logIn(page);
});

test('listado de clientes admite paginación, búsqueda y navegación al perfil', async ({
    page,
}, testInfo) => {
    await page.goto('/admin/customers');
    await expect(
        page.getByRole('heading', { name: 'Clientes', exact: true }),
    ).toBeVisible();
    await expect(page.getByText(/\d+ clientes/)).toBeVisible();

    await page
        .getByLabel('Buscar clientes por nombre o teléfono')
        .fill('Cliente paginado');
    await page.getByRole('button', { name: 'Buscar', exact: true }).click();
    await expect(page.getByText('30 clientes')).toBeVisible();

    const mobile = (page.viewportSize()?.width ?? 0) < 768;

    if (mobile) {
        await page.getByLabel('Orden de clientes').click();
        await page.getByRole('option', { name: 'Nombre Z–A' }).click();
    } else {
        await page
            .getByRole('button', {
                name: 'Ordenar por Nombre, descendente',
            })
            .click();
    }

    await expect(page).toHaveURL(/sort=name/);
    await expect(page).toHaveURL(/direction=desc/);
    const visibleCustomers = mobile
        ? page.locator('article')
        : page.locator('tbody tr');

    await expect(
        visibleCustomers.filter({ hasText: 'Cliente paginado 30' }),
    ).toHaveCount(1);
    await expect(
        visibleCustomers.filter({ hasText: 'Cliente paginado 06' }),
    ).toHaveCount(1);
    await expect(
        visibleCustomers.filter({ hasText: 'Cliente paginado 05' }),
    ).toHaveCount(0);

    await page.screenshot({
        path: screenshotPath(testInfo, 'customers'),
        fullPage: true,
        animations: 'disabled',
    });

    await page.getByRole('link', { name: 'Página siguiente' }).click();
    await expect(page).toHaveURL(/page=2/);
    await expect(page).toHaveURL(/sort=name/);
    await expect(page).toHaveURL(/direction=desc/);
    await expect(page.getByText('Página 2 de 2')).toBeVisible();
    await expect(
        visibleCustomers.filter({ hasText: 'Cliente paginado 05' }),
    ).toHaveCount(1);
    await expect(
        visibleCustomers.filter({ hasText: 'Cliente paginado 01' }),
    ).toHaveCount(1);
    await expect(
        visibleCustomers.filter({ hasText: 'Cliente paginado 30' }),
    ).toHaveCount(0);

    await page
        .getByLabel('Buscar clientes por nombre o teléfono')
        .fill('4400-0007');
    await page.getByRole('button', { name: 'Buscar', exact: true }).click();
    await expect(page).toHaveURL(/sort=name/);
    await expect(page).toHaveURL(/direction=desc/);
    await expect(page.getByText('1 cliente')).toBeVisible();
    await openCustomerFromList(page, 'Cliente paginado 07');
    await expect(page).toHaveURL(/\/admin\/customers\/\d+$/);
    await expect(page.getByText('Sin compras registradas')).toBeVisible();
});

test('perfil del cliente edita datos privados y abre el detalle compartido del pedido', async ({
    page,
}, testInfo) => {
    test.setTimeout(60_000);
    const mobile = testInfo.project.name === 'mobile-chromium';
    const fixtureId = `${testInfo.project.name}-${testInfo.parallelIndex}-${testInfo.retry}`;
    const customerName = `Cliente perfil ${fixtureId}`;
    const orderId = await createOrder(page, customerName);

    const customerLink = page.getByRole('link', {
        name: customerName,
        exact: true,
    });
    await expect(customerLink).toBeVisible();
    await customerLink.click();
    await expect(
        page.getByRole('heading', { name: customerName, exact: true }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Editar', exact: true }).click();
    const dialog = page.getByRole('dialog');
    const phoneSuffix =
        9000 +
        testInfo.parallelIndex * 10 +
        testInfo.retry * 2 +
        (mobile ? 1 : 0);
    const phone = `11 5555-${phoneSuffix}`;
    await dialog.getByLabel('Teléfono').fill(phone);
    await dialog.getByLabel('Dirección').fill('Calle Perfil 123');
    await dialog
        .getByLabel('Notas privadas')
        .fill('Prefiere retiro por la tarde.');
    await dialog.getByRole('button', { name: 'Guardar cambios' }).click();
    await expect(dialog).toHaveCount(0);

    await expect(page.getByText(phone)).toBeVisible();
    await expect(page.getByText('Calle Perfil 123')).toBeVisible();
    await expect(page.getByText('Prefiere retiro por la tarde.')).toBeVisible();
    await expect(page.getByRole('button', { name: orderId })).toBeVisible();

    await page.screenshot({
        path: screenshotPath(testInfo, 'customer-profile'),
        fullPage: true,
        animations: 'disabled',
    });

    await page.getByRole('button', { name: orderId }).click();
    const sheet = page.getByTestId('order-detail-sheet');
    await expect(sheet).toBeVisible();
    await expect(sheet.getByText('Orden de producción')).toBeVisible();
    await expect(sheet.getByText('Operaciones')).toBeVisible();
    await expect(
        sheet.getByRole('link', { name: /Ver detalle completo/ }),
    ).toBeVisible();

    await page.screenshot({
        path: screenshotPath(testInfo, 'customer-profile-order-detail'),
        fullPage: true,
        animations: 'disabled',
    });

    await page.keyboard.press('Escape');
    await expect(sheet).toHaveCount(0);
    await expect(page).toHaveURL(new RegExp(`/admin/customers/\\d+$`));
    await expect(
        page.getByRole('heading', { name: customerName }),
    ).toBeVisible();
});
