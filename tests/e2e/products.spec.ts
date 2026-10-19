import type { Page } from '@playwright/test';
import { expect, test } from './support/test';

const seededProducts = {
    Clásicas: ['Criolla Salada', 'Criolla Dulce', 'Jamón y Queso', 'Caprese'],
    Especiales: [
        'Salteña',
        'Humita Tucumana',
        'Hongos al puerro',
        'Pollo y portobellos',
        'Matambre y verduras asadas',
    ],
} as const;

async function logInAsAdministrator(page: Page) {
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

test('configuración abre la gestión de productos con el catálogo inicial', async ({
    page,
}) => {
    await logInAsAdministrator(page);

    await page.goto('/admin/settings');
    await expect(page).toHaveURL(/\/admin\/settings\/products$/);
    await expect(
        page.getByRole('heading', { name: 'Gestión de productos' }),
    ).toBeVisible();

    for (const product of seededProducts.Clásicas) {
        await expect(
            page.getByRole('heading', { name: product, exact: true }),
        ).toBeVisible();
    }

    await page.getByRole('button', { name: 'Especiales' }).click();

    for (const product of seededProducts.Especiales) {
        await expect(
            page.getByRole('heading', { name: product, exact: true }),
        ).toBeVisible();
    }

    const saltena = page.locator('[data-slot="card"]').filter({
        has: page.getByRole('heading', { name: 'Salteña', exact: true }),
    });
    await expect(
        saltena.getByText('Docena cocinada', { exact: true }),
    ).toBeVisible();
    await expect(saltena).toContainText(/18[.\s]000/);
});

test('administración permite crear, editar y eliminar etiquetas públicas', async ({
    page,
}) => {
    await logInAsAdministrator(page);
    await page.goto('/admin/settings/products');

    await page.getByRole('button', { name: 'Gestionar etiquetas' }).click();
    const dialog = page.getByRole('dialog', { name: 'Gestionar etiquetas' });
    await dialog.getByLabel('Nueva etiqueta').fill('Etiqueta de prueba');
    await dialog.getByRole('button', { name: 'Agregar' }).click();

    const row = dialog.locator('form:has(input[value="etiqueta-de-prueba"])');
    await expect(row.getByLabel('Nombre')).toHaveValue('Etiqueta de prueba');
    await row.getByLabel('Nombre').fill('Etiqueta editada');
    await row.getByRole('button', { name: 'Guardar' }).click();
    await expect(row.getByLabel('Nombre')).toHaveValue('Etiqueta editada');

    page.once('dialog', (confirmation) => confirmation.accept());
    await row
        .getByRole('button', { name: 'Eliminar etiqueta Etiqueta editada' })
        .click();
    await expect(row).toHaveCount(0);
});

test('configuración presenta el recargo por unidad y solo el descuento crudo de docena', async ({
    page,
}) => {
    await logInAsAdministrator(page);
    await page.goto('/admin/settings/general');

    await expect(
        page.getByRole('heading', { name: 'Configuración de pedidos' }),
    ).toBeVisible();
    await expect(page.getByLabel('Recargo por unidad (%)')).toHaveValue('10');
    await expect(page.getByLabel('Docena combinada cocinada')).toHaveCount(0);
    await expect(page.getByLabel('Docena combinada cruda')).toHaveCount(0);
    const rawDiscounts = page.getByRole('group', {
        name: 'Descuentos por preparación en crudo',
    });
    await expect(
        rawDiscounts.getByLabel('Docena', { exact: true }),
    ).toHaveValue('2000');
    await expect(
        rawDiscounts.getByLabel('Unidad', { exact: true }),
    ).toHaveCount(0);
    await expect(
        rawDiscounts.getByLabel('Media docena', { exact: true }),
    ).toHaveCount(0);
    await expect(page.getByText('Cooking surcharges')).toHaveCount(0);
    await expect(page.getByText(/recargo por cocción/i)).toHaveCount(0);
});

test('configuración mantiene ambos paneles dentro del viewport de escritorio', async ({
    page,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium');

    await logInAsAdministrator(page);
    await page.goto('/admin/settings/products');

    const navigationPanel = page.locator('[data-settings-panel="navigation"]');
    const contentPanel = page.locator('[data-settings-panel="content"]');

    await expect(navigationPanel).toBeVisible();
    await expect(contentPanel).toBeVisible();

    const layout = await page.evaluate(() => {
        const navigation = document.querySelector<HTMLElement>(
            '[data-settings-panel="navigation"]',
        );
        const content = document.querySelector<HTMLElement>(
            '[data-settings-panel="content"]',
        );

        if (!navigation || !content) {
            throw new Error('No se renderizaron los paneles de configuración.');
        }

        return {
            documentScrollHeight: document.documentElement.scrollHeight,
            viewportHeight: window.innerHeight,
            navigationBottom: navigation.getBoundingClientRect().bottom,
            contentBottom: content.getBoundingClientRect().bottom,
            contentOverflowY: getComputedStyle(content).overflowY,
        };
    });

    expect(layout.documentScrollHeight).toBeLessThanOrEqual(
        layout.viewportHeight,
    );
    expect(layout.navigationBottom).toBeLessThanOrEqual(layout.viewportHeight);
    expect(layout.contentBottom).toBeLessThanOrEqual(layout.viewportHeight);
    expect(layout.contentOverflowY).toBe('auto');
});

test('administrador crea, edita y cambia la disponibilidad de un producto', async ({
    page,
}, testInfo) => {
    const productName = `Producto Playwright ${testInfo.project.name}`;
    const editedName = `${productName} editado`;
    await logInAsAdministrator(page);

    await page.goto('/admin/settings/products');
    await expect(
        page.getByRole('heading', { name: 'Gestión de productos' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Criolla Salada' }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Agregar producto' }).click();
    const createDialog = page.getByRole('dialog', { name: 'Agregar producto' });
    await createDialog.getByLabel('Nombre', { exact: true }).fill(productName);
    await createDialog
        .getByLabel('Descripción breve')
        .fill('Creado mediante el flujo administrativo.');
    await createDialog.getByLabel('Docena', { exact: true }).fill('18000');
    // The empanada unit price is derived from the dozen and read-only: 18000 / 12 × 1.1 → $1.700.
    await expect(
        createDialog.getByLabel('Unidad (calculada)', { exact: true }),
    ).toHaveText(/1\.700/);
    // The half dozen is never entered: six units at that unit price → $10.200.
    await expect(
        createDialog.getByLabel('Media docena (calculada)', { exact: true }),
    ).toHaveText(/10\.200/);
    await expect(
        createDialog.getByRole('button', {
            name: 'Configurar media mediante recargo',
        }),
    ).toHaveCount(0);
    await createDialog.getByLabel('Orden dentro de la categoría').fill('99');
    const vegetarianTag = createDialog.getByRole('checkbox', {
        name: 'Vegetariana',
    });
    await vegetarianTag.focus();
    await vegetarianTag.press('Space');
    const createProduct = createDialog.getByRole('button', {
        name: 'Crear producto',
    });
    await createProduct.focus();
    await createProduct.press('Enter');

    await expect(createDialog).toBeHidden();
    const createdCard = page.locator('[data-slot="card"]').filter({
        has: page.getByRole('heading', { name: productName, exact: true }),
    });
    await expect(createdCard).toBeVisible();
    await expect(
        createdCard.getByText('Vegetariano', { exact: true }),
    ).toBeVisible();

    const editProduct = createdCard.getByRole('button', {
        name: 'Editar producto',
    });
    await editProduct.focus();
    await editProduct.press('Enter');
    const editDialog = page.getByRole('dialog', { name: 'Editar producto' });
    await editDialog.getByLabel('Nombre', { exact: true }).fill(editedName);
    const availability = editDialog.getByRole('checkbox', {
        name: /Disponible para la venta/,
    });
    await availability.focus();
    await availability.press('Space');
    const saveProduct = editDialog.getByRole('button', {
        name: 'Guardar cambios',
    });
    await saveProduct.focus();
    await saveProduct.press('Enter');

    await expect(editDialog).toBeHidden();
    const editedCard = page.locator('[data-slot="card"]').filter({
        has: page.getByRole('heading', { name: editedName, exact: true }),
    });
    await expect(editedCard).toBeVisible();
    await expect(
        editedCard.getByText('No disponible', { exact: true }),
    ).toBeVisible();
});
