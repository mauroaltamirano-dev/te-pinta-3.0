import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { expect, test } from './support/test';

function tinker(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_DATABASE:
                process.env.PLAYWRIGHT_DB_DATABASE ?? 'testing_playwright',
            DB_URL: '',
        },
        stdio: 'pipe',
    })
        .toString()
        .trim();
}

/** Table M1, free; returns its id. */
function createTable(): number {
    return Number(
        tinker(String.raw`
            echo App\Modules\Restaurant\Models\DiningArea::query()->create(['name' => 'Vereda', 'width' => 1600, 'height' => 1000, 'is_active' => true])
                ->tables()->create(['code' => 'M1', 'shape' => 'rectangle', 'position_x' => '0.1000', 'position_y' => '0.1000', 'width' => '0.1000', 'height' => '0.1000', 'rotation' => 0, 'is_active' => true])->id;
        `),
    );
}

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

async function sendRound(page: Page) {
    await page.getByRole('button', { name: 'Agregar 6 Salteña' }).click();
    await page.getByRole('button', { name: /Revisar ronda 1/ }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Confirmar y enviar a cocina' })
        .click();
}

test.beforeEach(({ page }, testInfo) => {
    void page;
    test.skip(
        testInfo.project.name !== 'chromium',
        'El aviso es el mismo en cualquier pantalla del panel.',
    );
});

test('un envío que el servidor rechaza muestra el aviso en lugar de la página de error', async ({
    page,
}) => {
    const table = createTable();
    await logIn(page);
    await page.goto(`/admin/tables/${table}`);
    await page.route('**/admin/tables/*/rounds', (route) =>
        route.fulfill({
            status: 500,
            contentType: 'text/html',
            body: '<h1>Server Error</h1>',
        }),
    );

    await sendRound(page);
    await expect(
        page.getByText(
            'No se pudo completar la acción (error 500). Probá de nuevo.',
        ),
    ).toBeVisible();
    await expect(page.locator('iframe')).toHaveCount(0);
    // The draft survives, so the waiter can retry.
    await expect(page.getByLabel('Cantidad de Salteña')).toHaveText('6');
});

test('un envío demasiado grande muestra un solo aviso', async ({ page }) => {
    const table = createTable();
    await logIn(page);
    await page.goto(`/admin/tables/${table}`);
    await page.route('**/admin/tables/*/rounds', (route) =>
        route.fulfill({
            status: 413,
            contentType: 'application/json',
            body: JSON.stringify({
                message: 'El archivo es demasiado grande.',
            }),
        }),
    );

    await sendRound(page);
    // The 413 toast and the failed-submission toast share one slot.
    await expect(page.locator('[data-sonner-toast]')).toHaveText([
        'El archivo es demasiado grande.',
    ]);
});

test('un envío demasiado grande sin mensaje del servidor igual avisa', async ({
    page,
}) => {
    await page.goto('/login');
    // A proxy can answer 413 with a body that is not the app's JSON object.
    await page.route('**/login', (route) =>
        route.request().method() === 'POST'
            ? route.fulfill({
                  status: 413,
                  contentType: 'application/json',
                  body: 'null',
              })
            : route.continue(),
    );

    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page.locator('[data-sonner-toast]')).toHaveText([
        'El archivo es demasiado grande.',
    ]);
});

test('un envío sin conexión muestra el aviso', async ({ page }) => {
    const table = createTable();
    await logIn(page);
    await page.goto(`/admin/tables/${table}`);
    await page.route('**/admin/tables/*/rounds', (route) => route.abort());

    await sendRound(page);
    await expect(
        page.getByText(
            'Sin conexión con el servidor. Revisá si se guardó antes de reintentar.',
        ),
    ).toBeVisible();
});

test('un envío con errores de validación muestra el motivo', async ({
    page,
}) => {
    const table = createTable();
    await logIn(page);
    await page.goto(`/admin/tables/${table}`);
    // Someone else opens the table while this screen still shows it free.
    tinker(String.raw`
        $owner = App\Modules\Identity\Models\User::where('email', 'playwright-admin@example.test')->firstOrFail();
        app(App\Modules\Orders\Actions\ConfirmTableRound::class)->execute(
            diningTableId: ${table}, expectedSessionId: null, kind: App\Modules\Orders\Enums\TableRoundKind::Order,
            items: [['product_id' => App\Modules\Products\Models\Product::where('slug', 'saltena')->value('id'), 'quantity' => 1]],
            addons: [], note: null, roundKey: (string) Illuminate\Support\Str::uuid(), actor: $owner,
        );
    `);

    await sendRound(page);
    await expect(
        page
            .locator('[data-sonner-toast]')
            .filter({ hasText: 'La mesa ya está abierta' }),
    ).toBeVisible();
});

test('el pedido rápido avisa si la sesión venció', async ({ page }) => {
    await logIn(page);
    await page.route('**/admin/orders', (route) =>
        route.request().method() === 'POST'
            ? route.fulfill({
                  status: 419,
                  contentType: 'text/html',
                  body: '<h1>Page Expired</h1>',
              })
            : route.continue(),
    );

    await page
        .getByRole('button', { name: 'Agregar 6 unidades de Salteña' })
        .click();
    await page
        .locator('aside')
        .filter({ hasText: 'Pedido actual' })
        .getByRole('button', { name: 'Continuar' })
        .click();
    const continuation = page.getByRole('dialog');
    await continuation.getByRole('button', { name: 'Mostrador' }).click();
    await continuation
        .getByRole('button', { name: 'Confirmar pedido' })
        .click();

    await expect(
        page
            .locator('[data-sonner-toast]')
            .filter({ hasText: 'La sesión venció' }),
    ).toBeVisible();
});

test('el editor del plano avisa si no pudo guardar', async ({ page }) => {
    await logIn(page);
    await page.goto('/admin/restaurant/floor');
    await page.route('**/admin/restaurant/areas', (route) =>
        route.fulfill({
            status: 403,
            contentType: 'application/json',
            body: JSON.stringify({ message: 'This action is unauthorized.' }),
        }),
    );

    await page.getByLabel('Nueva área', { exact: true }).fill('Patio');
    await page.getByRole('button', { name: 'Crear área', exact: true }).click();
    await expect(
        page
            .locator('[data-sonner-toast]')
            .filter({ hasText: 'No tenés permiso para hacer esto.' }),
    ).toBeVisible();
});

test('una respuesta que no es del servidor nunca cuenta como pedido creado', async ({
    page,
}) => {
    await logIn(page);
    await page.route('**/admin/orders', (route) =>
        route.request().method() === 'POST'
            ? route.fulfill({
                  status: 200,
                  contentType: 'text/html',
                  body: '<h1>Iniciar sesión</h1>',
              })
            : route.continue(),
    );

    await page
        .getByRole('button', { name: 'Agregar 6 unidades de Salteña' })
        .click();
    await page
        .locator('aside')
        .filter({ hasText: 'Pedido actual' })
        .getByRole('button', { name: 'Continuar' })
        .click();
    const continuation = page.getByRole('dialog');
    await continuation.getByRole('button', { name: 'Mostrador' }).click();
    await continuation
        .getByRole('button', { name: 'Confirmar pedido' })
        .click();

    await expect(
        page
            .locator('[data-sonner-toast]')
            .filter({ hasText: 'Respuesta inesperada del servidor' }),
    ).toBeVisible();
    await expect(
        continuation.getByRole('heading', { name: /TP-\d+/ }),
    ).toHaveCount(0);
});
