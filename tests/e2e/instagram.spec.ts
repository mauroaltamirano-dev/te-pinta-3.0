import { expect, test } from '@playwright/test';
import type { Locator, Page, TestInfo } from '@playwright/test';
import { createInstagramFixture } from './support/instagram-fixtures';
import type { InstagramFixture } from './support/instagram-fixtures';

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

async function openInstagram(
    page: Page,
    testInfo: TestInfo,
    scenario: string,
): Promise<InstagramFixture> {
    const fixture = createInstagramFixture(testInfo, scenario);
    await logIn(page);
    await page.goto('/admin/marketing/instagram');
    await expect(
        page.getByRole('heading', { name: 'Instagram', exact: true }),
    ).toBeVisible();

    return fixture;
}

function history(page: Page): Locator {
    return page.locator('section[aria-labelledby="history-title"]');
}

async function searchHistory(page: Page, value: string) {
    const section = history(page);
    await section.getByLabel('Buscar por nombre de historia').fill(value);
    await section.getByRole('button', { name: 'Buscar', exact: true }).click();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('search') === value,
    );
}

async function openPublicationMenu(page: Page, name: string) {
    await history(page)
        .getByRole('button', {
            name: `Acciones para la publicación ${name}`,
            exact: true,
        })
        .click();
}

test('operaciones de Instagram se mantienen legibles en desktop y mobile', async ({
    page,
}, testInfo) => {
    const fixture = await openInstagram(page, testInfo, 'instagram-responsive');
    const schedules = page.locator(
        'section[aria-labelledby="schedules-title"]',
    );
    const section = history(page);

    await expect(
        schedules.getByText(/\d+ historias programadas a las 19:00\./),
    ).toBeVisible();
    await expect(
        schedules.getByText(fixture.schedules.first.name),
    ).toBeVisible();
    await expect(
        page.getByRole('img', {
            name: `Vista previa de ${fixture.assets.in_use.name}`,
        }),
    ).toBeVisible();
    await expect(section.getByRole('search')).toBeVisible();
    await expect(
        section.getByLabel('Filtrar historial por estado'),
    ).toBeVisible();
    await expect(
        section.getByLabel('Filtrar historial por período'),
    ).toBeVisible();

    const publishedName = fixture.publications.published.name;

    if (testInfo.project.name.includes('mobile')) {
        await expect(section.getByRole('table')).toBeHidden();
        await expect(
            section.locator('article').filter({ hasText: publishedName }),
        ).toBeVisible();
    } else {
        await expect(section.getByRole('table')).toBeVisible();
        await expect(
            section
                .getByRole('table')
                .getByText(publishedName, { exact: true }),
        ).toBeVisible();
    }

    await page.screenshot({
        path: testInfo.outputPath(
            `instagram-polish-${testInfo.project.name.includes('mobile') ? 'mobile' : 'desktop'}.png`,
        ),
        fullPage: true,
    });
});

test('filtros del historial muestran errores útiles y protegen resultados ambiguos', async ({
    page,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium');
    const fixture = await openInstagram(page, testInfo, 'instagram-history');
    const section = history(page);

    await section.getByLabel('Filtrar historial por estado').click();
    await page.getByRole('option', { name: 'Error', exact: true }).click();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('status') === 'failed',
    );
    await expect(
        section
            .getByRole('table')
            .getByText(fixture.publications.failed.name, { exact: true }),
    ).toBeVisible();
    await expect(
        section
            .getByRole('table')
            .getByText(fixture.publications.ambiguous.name, { exact: true }),
    ).toBeVisible();
    await expect(
        section.getByText(fixture.publications.published.name, { exact: true }),
    ).toHaveCount(0);

    await searchHistory(page, fixture.publications.failed.name);
    await expect(
        section.getByText(fixture.publications.ambiguous.name, { exact: true }),
    ).toHaveCount(0);
    await openPublicationMenu(page, fixture.publications.failed.name);
    await expect(
        page.getByRole('menuitem', { name: 'Reintentar', exact: true }),
    ).toBeVisible();
    await page.keyboard.press('Escape');
    await openPublicationMenu(page, fixture.publications.failed.name);
    await page
        .getByRole('menuitem', { name: 'Ver error', exact: true })
        .click();

    let detail = page.getByRole('dialog').filter({
        has: page.getByRole('heading', {
            name: 'Detalle de publicación',
            exact: true,
        }),
    });
    await expect(
        detail.getByText('Error reintentable', { exact: true }),
    ).toBeVisible();
    await expect(detail).toContainText(
        'La conexión con Meta necesita renovarse.',
    );
    await expect(
        detail.getByText('Reintentable', { exact: true }),
    ).toBeVisible();
    await detail.getByRole('button', { name: 'Detalles técnicos' }).click();
    await expect(detail).toContainText('El token de Meta había expirado.');
    await expect(detail).toContainText('meta_token_expired');
    await page.keyboard.press('Escape');
    await expect(detail).toHaveCount(0);

    await searchHistory(page, fixture.publications.ambiguous.name);
    await openPublicationMenu(page, fixture.publications.ambiguous.name);
    await expect(
        page.getByRole('menuitem', { name: 'Reintentar', exact: true }),
    ).toHaveCount(0);
    await page
        .getByRole('menuitem', { name: 'Ver error', exact: true })
        .click();
    detail = page.getByRole('dialog').filter({
        has: page.getByRole('heading', {
            name: 'Detalle de publicación',
            exact: true,
        }),
    });
    await expect(
        detail.getByText('Revisión necesaria.', { exact: true }),
    ).toBeVisible();
    await expect(detail).toContainText(
        'Meta pudo haber recibido esta publicación. Revisala antes de volver a enviarla para evitar duplicados.',
    );
});

test('archivar requiere confirmación y conserva la publicación auditable', async ({
    page,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium');
    const fixture = await openInstagram(page, testInfo, 'instagram-archive');
    const publication = fixture.publications.archive;
    const section = history(page);

    await section.getByLabel('Filtrar historial por período').click();
    await page.getByRole('option', { name: 'Todo', exact: true }).click();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('period') === 'all',
    );
    await searchHistory(page, publication.name);
    await openPublicationMenu(page, publication.name);
    await page.getByRole('menuitem', { name: 'Archivar', exact: true }).click();

    const confirmation = page.getByRole('dialog').filter({
        has: page.getByRole('heading', {
            name: 'Archivar publicación',
            exact: true,
        }),
    });
    await expect(confirmation).toContainText(
        'Se ocultará del historial principal sin borrar su auditoría.',
    );
    const archived = page.waitForResponse(
        (response) =>
            response.request().method() === 'PATCH' &&
            response.url().endsWith(`/publications/${publication.id}/archive`),
    );
    await confirmation
        .getByRole('button', { name: 'Archivar', exact: true })
        .click();
    expect((await archived).status()).toBe(303);
    await expect(
        section.getByText(publication.name, { exact: true }),
    ).toHaveCount(0);

    await section.getByLabel('Mostrar archivadas').click();
    await expect(page).toHaveURL(
        (url) => url.searchParams.get('show_archived') === '1',
    );
    await expect(section.getByLabel('Mostrar archivadas')).toBeChecked();
    await expect(
        section.getByRole('table').getByText(publication.name, { exact: true }),
    ).toBeVisible();
});

test('una historia en uso identifica cada programación activa que bloquea', async ({
    page,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium');
    const fixture = await openInstagram(page, testInfo, 'instagram-asset-use');

    await page
        .getByRole('button', {
            name: `Acciones para ${fixture.assets.in_use.name}`,
            exact: true,
        })
        .click();
    await page.getByRole('menuitem', { name: 'Eliminar', exact: true }).click();

    const dialog = page.getByRole('dialog').filter({
        has: page.getByRole('heading', {
            name: 'Eliminar historia',
            exact: true,
        }),
    });
    await expect(dialog).toContainText(
        'Esta historia está siendo usada por 2 programaciones.',
    );
    await expect(dialog).toContainText(fixture.schedules.first.name);
    await expect(dialog).toContainText(fixture.schedules.second.name);
    await expect(
        dialog.getByRole('button', { name: 'Eliminar definitivamente' }),
    ).toHaveCount(0);
});

test('programaciones simultáneas conservan orden explícito y edición determinista', async ({
    page,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium');
    const fixture = await openInstagram(page, testInfo, 'instagram-same-time');
    const section = page.locator('section[aria-labelledby="schedules-title"]');

    await expect(
        section.getByText(/\d+ historias programadas a las 19:00\./),
    ).toBeVisible();
    let cardTexts = await section.getByRole('button').allTextContents();
    expect(
        cardTexts.findIndex((text) =>
            text.includes(fixture.schedules.first.name),
        ),
    ).toBeLessThan(
        cardTexts.findIndex((text) =>
            text.includes(fixture.schedules.second.name),
        ),
    );

    await section
        .getByRole('button')
        .filter({ hasText: fixture.schedules.first.name })
        .click();
    const dialog = page.getByRole('dialog').filter({
        has: page.getByRole('heading', {
            name: 'Editar programación',
            exact: true,
        }),
    });
    const editedName = `${fixture.schedules.first.name} editada`;
    await dialog.getByLabel('Nombre').fill(editedName);
    await dialog.getByLabel('Orden cuando comparte horario').fill('3');
    const updated = page.waitForResponse(
        (response) =>
            response.request().method() === 'PATCH' &&
            response.url().endsWith(`/schedules/${fixture.schedules.first.id}`),
    );
    await dialog
        .getByRole('button', { name: 'Guardar programación', exact: true })
        .click();
    expect((await updated).status()).toBe(303);
    await expect(section.getByText(editedName, { exact: true })).toBeVisible();

    cardTexts = await section.getByRole('button').allTextContents();
    expect(
        cardTexts.findIndex((text) =>
            text.includes(fixture.schedules.second.name),
        ),
    ).toBeLessThan(cardTexts.findIndex((text) => text.includes(editedName)));
});

test('crear programación acepta todos los días ISO y de martes a domingo', async ({
    page,
}, testInfo) => {
    test.skip(testInfo.project.name !== 'chromium');
    const fixture = await openInstagram(page, testInfo, 'instagram-weekdays');
    const section = page.locator('section[aria-labelledby="schedules-title"]');

    const createSchedule = async (name: string, excludeMonday: boolean) => {
        await section
            .getByRole('button', { name: 'Agregar', exact: true })
            .click();
        const dialog = page.getByRole('dialog').filter({
            has: page.getByRole('heading', {
                name: 'Nueva programación',
                exact: true,
            }),
        });
        await dialog.getByLabel('Nombre').fill(name);
        await dialog.getByLabel('Horario', { exact: true }).fill('20:00');
        await expect(
            dialog.getByLabel('Orden cuando comparte horario'),
        ).toHaveValue('1');
        await dialog.getByLabel('Historia', { exact: true }).click();
        await page
            .getByRole('option', {
                name: fixture.assets.replacement.name,
                exact: true,
            })
            .click();

        for (const day of [
            'Lunes',
            'Martes',
            'Miércoles',
            'Jueves',
            'Viernes',
            'Sábado',
            'Domingo',
        ]) {
            await expect(
                dialog.getByRole('checkbox', { name: day, exact: true }),
            ).toBeChecked();
        }

        if (excludeMonday) {
            await dialog
                .getByRole('checkbox', { name: 'Lunes', exact: true })
                .uncheck();
        }

        const created = page.waitForResponse(
            (response) =>
                response.request().method() === 'POST' &&
                response.url().endsWith('/schedules'),
        );
        await dialog
            .getByRole('button', { name: 'Guardar programación', exact: true })
            .click();
        expect([302, 303]).toContain((await created).status());
        await expect(dialog).toHaveCount(0);
        await expect(section.getByText(name, { exact: true })).toBeVisible();
    };

    await createSchedule(`${fixture.identity} todos los días`, false);
    await createSchedule(`${fixture.identity} martes a domingo`, true);
});
