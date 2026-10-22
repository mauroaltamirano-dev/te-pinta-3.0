import type { Page } from '@playwright/test';
import { expect, test } from './support/test';

declare global {
    interface Window {
        previewGates: { release: () => void; parsed: Promise<unknown> }[];
    }
}

let category: string;
let firstItem: string;
let secondItem: string;
const preview = (page: Page) =>
    page.getByRole('region', { name: 'Vista previa del ajuste' });
const confirm = (page: Page) =>
    page.getByRole('button', { name: 'Confirmar ajuste', exact: true });
const calculate = (page: Page) =>
    page.getByRole('button', { name: 'Ver vista previa', exact: true });

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

async function openForm(page: Page) {
    await page.goto('/admin/inventory/adjustments');
    await page
        .locator('#adjustment-inventory_item_id')
        .selectOption({ label: `${firstItem} · ${category}` });
    await page.locator('#adjustment-unit_id').selectOption({ label: 'Gramo' });
    await page.locator('#adjustment-quantity').fill('100');
    await page.locator('#adjustment-explicit_total_cost').fill('1000');
}

// Delay actual response bodies after headers: aborting fetch cannot solve this race.
async function delayBodies(page: Page) {
    await page.evaluate(() => {
        const fetch = window.fetch.bind(window);
        window.previewGates = [];
        window.fetch = async (...args) => {
            const response = await fetch(...args);

            if (String(args[0]).endsWith('/adjustments/preview')) {
                const json = response.json.bind(response);
                response.json = () => {
                    let release!: () => void;
                    const gate = new Promise<void>((resolve) => {
                        release = resolve;
                    });
                    const parsed = gate.then(json);
                    window.previewGates.push({ release, parsed });

                    return parsed;
                };
            }

            return response;
        };
    });
}

async function requestDelayed(page: Page, count: number) {
    await calculate(page).click();
    await page.waitForFunction((n) => window.previewGates.length === n, count);
}

async function releaseBody(page: Page, index: number) {
    await page.evaluate(async (i) => {
        const gate = window.previewGates[i];
        gate.release();
        await gate.parsed;
        await new Promise(requestAnimationFrame);
    }, index);
}

test.beforeEach(async ({ page }, info) => {
    category = `AUX preview ${info.project.name} ${info.workerIndex}`;
    firstItem = `${category} first`;
    secondItem = `${category} second`;
    await login(page);
    await page.goto('/admin/inventory');
    await page
        .getByRole('button', { name: 'Nueva categoría', exact: true })
        .click();
    const dialog = page.getByRole('dialog', {
        name: 'Nueva categoría',
        exact: true,
    });
    await dialog.getByLabel('Nombre', { exact: true }).fill(category);
    await dialog.getByRole('button', { name: 'Guardar categoría' }).click();
    await expect(dialog).toBeHidden();

    for (const name of [firstItem, secondItem]) {
        await page
            .getByRole('button', { name: 'Nuevo insumo', exact: true })
            .click();
        const item = page.getByRole('dialog', {
            name: 'Nuevo insumo',
            exact: true,
        });
        await item.getByLabel('Nombre', { exact: true }).fill(name);
        await item
            .getByLabel('Categoría', { exact: true })
            .selectOption({ label: category });
        await item.getByLabel('Clasificación').selectOption('raw_material');
        await item
            .getByLabel('Unidad de ingreso')
            .selectOption({ label: 'Kilogramo' });
        await item.getByRole('button', { name: 'Guardar insumo' }).click();
        await expect(item).toBeHidden();
    }
});

test.beforeEach(async ({ page }) => {
    await openForm(page);
});

const changes: [string, (page: Page) => Promise<unknown>][] = [
    ['quantity', (page) => page.locator('#adjustment-quantity').fill('200')],
    [
        'item',
        (page) =>
            page
                .locator('#adjustment-inventory_item_id')
                .selectOption({ label: `${secondItem} · ${category}` }),
    ],
    ['type', (page) => page.locator('#adjustment-type').selectOption('waste')],
    [
        'explicit cost',
        (page) => page.locator('#adjustment-explicit_total_cost').fill('2000'),
    ],
    [
        'unit',
        (page) =>
            page
                .locator('#adjustment-unit_id')
                .selectOption({ label: 'Kilogramo' }),
    ],
    [
        'date',
        (page) => page.locator('#adjustment-effective_date').fill('2026-09-01'),
    ],
    ['note', (page) => page.locator('#adjustment-note').fill('Updated reason')],
];

for (const [field, change] of changes) {
    test(`${field} invalidates accepted and delayed previews`, async ({
        page,
    }) => {
        await calculate(page).click();
        await expect(confirm(page)).toBeEnabled();
        await change(page);
        await expect(preview(page)).toHaveCount(0);
        await expect(confirm(page)).toHaveCount(0);
        await expect(
            page
                .getByRole('status')
                .filter({ hasText: 'Datos pendientes de validación.' }),
        ).toBeVisible();
        await openForm(page);
        await delayBodies(page);
        await requestDelayed(page, 1);
        await change(page);
        await releaseBody(page, 0);
        await expect(preview(page)).toHaveCount(0);
        await expect(confirm(page)).toHaveCount(0);
    });
}

test('loading blocks confirmation, then the current response permits it', async ({
    page,
}) => {
    await delayBodies(page);
    await requestDelayed(page, 1);
    await expect(confirm(page)).toHaveCount(0);
    await expect(page.locator('form[aria-busy=true]')).toBeVisible();
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Calculando la vista previa' }),
    ).toBeVisible();
    await releaseBody(page, 0);
    await expect(confirm(page)).toBeEnabled();
    await expect(preview(page)).toContainText('Stock resultante: 100 g');
    await expect(
        page
            .getByRole('status')
            .filter({ hasText: 'Vista previa actualizada.' }),
    ).toBeVisible();
});

test('obsolete completion cannot clear a newer pending request', async ({
    page,
}) => {
    await delayBodies(page);
    await requestDelayed(page, 1);
    await page.locator('#adjustment-quantity').fill('200');
    await requestDelayed(page, 2);
    await releaseBody(page, 0);
    await expect(preview(page)).toHaveCount(0);
    await expect(page.locator('form[aria-busy=true]')).toBeVisible();
    await releaseBody(page, 1);
    await expect(confirm(page)).toBeEnabled();
    await expect(preview(page)).toContainText('Stock resultante: 200 g');
});

test('out-of-order responses preserve the newest accepted preview', async ({
    page,
}) => {
    await delayBodies(page);
    await requestDelayed(page, 1);
    await page.locator('#adjustment-quantity').fill('200');
    await requestDelayed(page, 2);
    await releaseBody(page, 1);
    await expect(confirm(page)).toBeEnabled();
    await releaseBody(page, 0);
    await expect(preview(page)).toContainText('Stock resultante: 200 g');
    await expect(confirm(page)).toBeEnabled();
});

test('returning to the same inputs does not revive an older request', async ({
    page,
}) => {
    await delayBodies(page);
    await requestDelayed(page, 1);
    await page.locator('#adjustment-quantity').fill('200');
    await page.locator('#adjustment-quantity').fill('100');
    await releaseBody(page, 0);
    await expect(confirm(page)).toHaveCount(0);
    await requestDelayed(page, 2);
    await releaseBody(page, 1);
    await expect(confirm(page)).toBeEnabled();
});

test('current validation errors leave confirmation unavailable', async ({
    page,
}) => {
    await page.locator('#adjustment-quantity').fill('-1');
    await calculate(page).click();
    await expect(page.locator('#adjustment-quantity')).toHaveAttribute(
        'aria-invalid',
        'true',
    );
    await expect(confirm(page)).toHaveCount(0);
    await expect(preview(page)).toHaveCount(0);
});

test('delayed validation errors cannot overwrite a newer success', async ({
    page,
}) => {
    await page.locator('#adjustment-quantity').fill('-1');
    await delayBodies(page);
    await requestDelayed(page, 1);
    await page.locator('#adjustment-quantity').fill('200');
    await requestDelayed(page, 2);
    await releaseBody(page, 1);
    await expect(confirm(page)).toBeEnabled();
    await releaseBody(page, 0);
    await expect(page.locator('#adjustment-quantity')).toHaveAttribute(
        'aria-invalid',
        'false',
    );
    await expect(preview(page)).toContainText('Stock resultante: 200 g');
});

test('preview does not mutate stock and confirmation recomputes against real stock', async ({
    page,
}) => {
    await calculate(page).click();
    await expect(preview(page)).toContainText('Delta: 100 g');
    const stock = await page.context().newPage();
    await stock.goto('/admin/inventory');
    await expect(
        stock.getByRole('article', { name: firstItem, exact: true }),
    ).toContainText('0 g');
    const other = await page.context().newPage();
    await openForm(other);
    await other.locator('#adjustment-type').selectOption('manual_adjustment');
    await other.locator('#adjustment-quantity').fill('10');
    await other.locator('#adjustment-explicit_total_cost').fill('100');
    await calculate(other).click();
    await confirm(other).click();
    await expect(other).toHaveURL(/\/admin\/inventory\/adjustments\/\d+$/);
    await confirm(page).click();
    await expect(page).toHaveURL(/\/admin\/inventory\/adjustments\/\d+$/);
    await expect(page.getByRole('article')).toContainText(
        'Stock al registrar: 10 g',
    );
    await expect(page.getByRole('article')).toContainText('Movimiento: 90 g');
    await stock.reload();
    await expect(
        stock.getByRole('article', { name: firstItem, exact: true }),
    ).toContainText('100 g');
    await stock.close();
    await other.close();
});
