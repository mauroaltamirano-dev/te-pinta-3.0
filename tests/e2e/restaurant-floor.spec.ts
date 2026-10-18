import type { Page } from '@playwright/test';
import { expect, test } from './support/test';

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
    await page.goto('/admin/restaurant/floor');
}

async function createArea(page: Page, name: string) {
    await page.getByLabel('Nueva área', { exact: true }).fill(name);
    await page.getByRole('button', { name: 'Crear área', exact: true }).click();
    await expect(page.getByLabel('Nombre del área')).toHaveValue(name);
}

async function save(page: Page) {
    await page
        .getByRole('button', { name: 'Guardar cambios', exact: true })
        .click();
    await expect(page.getByRole('status')).toContainText('Cambios guardados.');
    await expect(
        page.getByRole('button', { name: 'Guardar cambios', exact: true }),
    ).toBeDisabled();
}

async function selectArea(page: Page, name: string) {
    await page
        .getByLabel('Área', { exact: true })
        .selectOption({ label: name });
}

test('desktop: six tables, pointer move/resize, two columns and persisted coordinates', async ({
    page,
}, info) => {
    test.skip(info.project.name !== 'chromium', 'Desktop editing proof');
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page);
    const name = `Dining floor ${Date.now()}`;
    await createArea(page, name);

    for (let index = 0; index < 6; index++) {
        await page
            .getByRole('button', { name: 'Agregar mesa', exact: true })
            .click();
        await page
            .getByLabel('Posición X', { exact: true })
            .fill(String(0.1 + (index % 3) * 0.3));
        await page
            .getByLabel('Posición Y', { exact: true })
            .fill(index < 3 ? '0.15' : '0.65');
        await page.getByLabel('Capacidad', { exact: true }).fill('4');
    }

    await page.getByRole('button', { name: 'Mesa 2', exact: true }).click();
    const plan = page.getByTestId('floor-plan');
    const bounds = await plan.boundingBox();
    expect(bounds).not.toBeNull();
    const button = await page
        .getByRole('button', { name: 'Mesa 2', exact: true })
        .boundingBox();
    const writes: string[] = [];
    page.on('request', (request) => {
        if (request.method() === 'PUT' && request.url().endsWith('/layout')) {
            writes.push(request.url());
        }
    });
    await page.mouse.move(
        button!.x + button!.width / 2,
        button!.y + button!.height / 2,
    );
    await page.mouse.down();
    await page.mouse.move(
        button!.x + button!.width / 2 + bounds!.width * 0.08,
        button!.y + button!.height / 2 + bounds!.height * 0.04,
        { steps: 8 },
    );
    await page.mouse.up();
    const xAfterDrag = await page
        .getByLabel('Posición X', { exact: true })
        .inputValue();
    expect(Number(xAfterDrag)).toBeGreaterThan(0.45);
    const handle = await page
        .getByRole('button', { name: 'Redimensionar Mesa 2', exact: true })
        .boundingBox();
    await page.mouse.move(
        handle!.x + handle!.width / 2,
        handle!.y + handle!.height / 2,
    );
    await page.mouse.down();
    await page.mouse.move(
        handle!.x + handle!.width / 2 + 10,
        handle!.y + handle!.height / 2 + 10,
        { steps: 5 },
    );
    await page.mouse.up();
    expect(
        Number(await page.getByLabel('Ancho', { exact: true }).inputValue()),
    ).toBeGreaterThan(0.15);
    expect(writes).toHaveLength(0);
    await page.getByLabel('Número / código').fill('20');
    await page.getByLabel('Forma', { exact: true }).selectOption('circle');
    await page.getByLabel('Rotación', { exact: true }).selectOption('45');
    const finalX = await page
        .getByLabel('Posición X', { exact: true })
        .inputValue();

    for (let index = 0; index < 2; index++) {
        await page
            .getByRole('button', { name: 'Agregar obstáculo', exact: true })
            .click();
        await page
            .getByLabel('Etiqueta del obstáculo')
            .fill(`Column ${index + 1}`);
        await page.getByLabel('Posición X', { exact: true }).fill('0.45');
        await page
            .getByLabel('Posición Y', { exact: true })
            .fill(index ? '0.5' : '0.3');
    }

    await save(page);
    expect(writes).toHaveLength(1);
    await page.reload();
    await selectArea(page, name);
    await expect(plan.locator('[data-floor-item="table"]')).toHaveCount(6);
    await expect(plan.locator('[data-floor-item="obstacle"]')).toHaveCount(2);
    await page.getByRole('button', { name: 'Mesa 20', exact: true }).click();
    await expect(page.getByLabel('Posición X', { exact: true })).toHaveValue(
        finalX,
    );
    await expect(page.getByLabel('Forma', { exact: true })).toHaveValue(
        'circle',
    );
    await expect(page.getByLabel('Rotación', { exact: true })).toHaveValue(
        '45',
    );
    await expect(page.getByLabel('Capacidad', { exact: true })).toHaveValue(
        '4',
    );
    await page.screenshot({
        path: 'storage/app/pos2-desktop.png',
        fullPage: true,
    });
});

for (const width of [320, 360, 390, 393, 1280, 1920]) {
    test(`responsive renderer and accessible controls at ${width}`, async ({
        page,
    }, info) => {
        test.skip(
            info.project.name !== 'chromium',
            'Explicit viewport matrix is covered once',
        );
        await page.setViewportSize({ width, height: 900 });
        await login(page);
        await createArea(page, `Viewport ${width} ${Date.now()}`);
        await page
            .getByRole('button', { name: 'Agregar mesa', exact: true })
            .click();
        await page.getByLabel('Ancho', { exact: true }).fill('0.25');
        await page.getByLabel('Alto', { exact: true }).fill('0.25');
        await save(page);
        await page.getByRole('button', { name: 'Mesa 1', exact: true }).click();
        await expect(page.getByLabel('Número / código')).toHaveValue('1');
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
        const floor = await page.getByTestId('floor-plan').boundingBox();
        expect(floor!.width / floor!.height).toBeCloseTo(1.6, 1);

        for (const label of [
            'Guardar cambios',
            'Agregar mesa',
            'Agregar obstáculo',
            'Mesa 1',
        ]) {
            const bounds = await page
                .getByRole('button', { name: label, exact: true })
                .boundingBox();
            expect(bounds!.height).toBeGreaterThanOrEqual(44);
        }

        await page
            .getByRole('button', { name: 'Vista de lectura', exact: true })
            .click();
        await page.getByRole('button', { name: 'Mesa 1', exact: true }).click();
        await expect(
            page.getByLabel('Posición X', { exact: true }),
        ).toBeDisabled();
        await expect(
            page.getByRole('button', {
                name: 'Redimensionar Mesa 1',
                exact: true,
            }),
        ).toHaveCount(0);

        if (width === 320) {
            await page.screenshot({
                path: 'storage/app/pos2-mobile-320.png',
                fullPage: true,
            });
        }
    });
}

test('Pixel 7: touch selection and basic changes persist without overflow', async ({
    page,
}, info) => {
    test.skip(info.project.name !== 'mobile-chromium', 'Real touch project');
    await login(page);
    const name = `Touch floor ${Date.now()}`;
    await createArea(page, name);
    await page.getByRole('button', { name: 'Agregar mesa', exact: true }).tap();
    await page.getByLabel('Ancho', { exact: true }).fill('0.25');
    await page.getByLabel('Alto', { exact: true }).fill('0.25');
    await save(page);
    await page.getByRole('button', { name: 'Mesa 1', exact: true }).tap();
    await page.getByLabel('Nombre opcional').fill('Patio 1');
    await page.getByLabel('Posición X', { exact: true }).fill('0.35');
    await page.getByLabel('Mesa activa').uncheck();
    await save(page);
    await page.reload();
    await selectArea(page, name);
    await page
        .getByRole('button', { name: 'Patio 1, inactiva', exact: true })
        .tap();
    await expect(page.getByLabel('Posición X', { exact: true })).toHaveValue(
        '0.3500',
    );
    await expect(page.getByLabel('Mesa activa')).not.toBeChecked();
    await expect(
        page.getByTestId('floor-plan').getByText('Inactiva', { exact: true }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await page.screenshot({
        path: 'storage/app/pos2-pixel7.png',
        fullPage: true,
    });
});

test('two administrators: explicit conflict preserves stale draft until intentional reload', async ({
    page,
    context,
}, info) => {
    test.skip(info.project.name !== 'chromium', 'Single concurrency proof');
    await login(page);
    const name = `Concurrent floor ${Date.now()}`;
    await createArea(page, name);
    await page
        .getByRole('button', { name: 'Agregar mesa', exact: true })
        .click();
    await save(page);
    const second = await context.newPage();
    await second.goto('/admin/restaurant/floor');
    await selectArea(second, name);
    await second.getByRole('button', { name: 'Mesa 1', exact: true }).click();
    await second.getByLabel('Posición X', { exact: true }).fill('0.6');
    await page.getByRole('button', { name: 'Mesa 1', exact: true }).click();
    await page.getByLabel('Posición X', { exact: true }).fill('0.4');
    await save(page);
    await second
        .getByRole('button', { name: 'Guardar cambios', exact: true })
        .click();
    await expect(second.getByRole('alert')).toContainText(
        'Tu borrador se conserva',
    );
    await expect(second.getByLabel('Posición X', { exact: true })).toHaveValue(
        '0.6',
    );
    await expect(
        second.getByRole('button', { name: 'Guardar cambios', exact: true }),
    ).toBeDisabled();
    second.once('dialog', (dialog) => dialog.accept());
    await second
        .getByRole('button', { name: 'Recargar plano', exact: true })
        .click();
    await selectArea(second, name);
    await second.getByRole('button', { name: 'Mesa 1', exact: true }).click();
    await expect(second.getByLabel('Posición X', { exact: true })).toHaveValue(
        '0.4000',
    );
    await second.close();
});
