import { execFileSync } from 'node:child_process';
import type { Page } from '@playwright/test';
import { expect, test } from './support/test';

function tinker(code: string): void {
    execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        env: {
            ...process.env,
            APP_ENV: 'testing',
            DB_DATABASE:
                process.env.PLAYWRIGHT_DB_DATABASE ?? 'testing_playwright',
            DB_URL: '',
        },
        stdio: 'pipe',
    });
}

function createCounterOrder(): void {
    tinker(String.raw`
        $actor = App\Modules\Identity\Models\User::where('email', 'playwright-admin@example.test')->firstOrFail();
        $product = App\Modules\Products\Models\Product::where('slug', 'playwright-unit-beverage')->firstOrFail();
        $data = new App\Modules\Orders\Data\OrderCheckoutData(
            items: [['product_id' => $product->id, 'quantity' => 3]], addons: [],
            condition: App\Modules\Orders\Enums\OrderCondition::Cooked,
            deliveryType: App\Modules\Orders\Enums\DeliveryType::DineIn,
            deliveryPeriod: null, deliveryDate: null, deliveryTime: null,
            deliveryAddress: null, notes: 'Sin hielo', customerId: null, customerName: null,
            customerPhone: null, customerPhoneNormalized: null, customerAddress: null,
            paymentStatus: App\Modules\Orders\Enums\PaymentStatus::Unpaid,
        );
        app(App\Modules\Orders\Actions\CreateOrder::class)->execute($data, App\Modules\Orders\Enums\OrderSource::Pos, $actor->id);
    `);
}

/** Table M1: 6 salteñas in one round and 6 in another, then the bill. */
function billTwoRoundsOfSaltenas(): void {
    tinker(String.raw`
        $actor = App\Modules\Identity\Models\User::where('email', 'playwright-admin@example.test')->firstOrFail();
        $saltena = App\Modules\Products\Models\Product::where('slug', 'saltena')->firstOrFail();
        $table = App\Modules\Restaurant\Models\DiningArea::query()->create(['name' => 'Vereda', 'width' => 1600, 'height' => 1000, 'is_active' => true])
            ->tables()->create(['code' => 'M1', 'shape' => 'rectangle', 'position_x' => '0.1000', 'position_y' => '0.1000', 'width' => '0.1000', 'height' => '0.1000', 'rotation' => 0, 'is_active' => true]);
        $round = fn (?int $session) => app(App\Modules\Orders\Actions\ConfirmTableRound::class)->execute(
            diningTableId: $table->id, expectedSessionId: $session, kind: App\Modules\Orders\Enums\TableRoundKind::Order,
            items: [['product_id' => $saltena->id, 'quantity' => 6]], addons: [], note: null,
            roundKey: (string) Illuminate\Support\Str::uuid(), actor: $actor,
        );
        $first = $round(null);
        $round($first->table_session_id);
        app(App\Modules\Orders\Actions\IssueTableBill::class)->execute($first->session()->firstOrFail(), $actor);
    `);
}

/** Replaces print() in every frame: each call records the document the printer would get. */
async function capturePrints(page: Page) {
    await page.addInitScript(() => {
        window.print = function () {
            const top = window.top as Window & { printed?: string[] };
            top.printed = [
                ...(top.printed ?? []),
                document.documentElement.outerHTML,
            ];
        };
    });

    return () =>
        page.evaluate(
            () => (window as Window & { printed?: string[] }).printed ?? [],
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

test.beforeEach(({ page }, testInfo) => {
    void page;
    test.skip(
        testInfo.project.name !== 'chromium',
        'La estación de impresión corre en la PC del local.',
    );
});

test('la estación imprime cada comanda una vez, sin precios, y reimprime a pedido', async ({
    page,
}) => {
    createCounterOrder();
    const printed = await capturePrints(page);
    await logIn(page);

    await page.goto('/admin/print-station');
    await expect(page.getByTestId('pending-count')).toHaveText('1');
    await expect(
        page
            .getByRole('alert')
            .filter({ hasText: 'Esta PC no está imprimiendo' }),
    ).toBeVisible();
    await expect(page).toHaveTitle(/^\(1\) Estación de impresión/);
    expect(await printed()).toHaveLength(0);

    await page
        .getByRole('button', { name: 'Activar esta PC como estación' })
        .click();
    await expect(page.getByTestId('pending-count')).toHaveText('0');
    await expect(page.getByTestId('station-state')).toHaveText('Imprimiendo');
    await expect(page.getByText('Impreso ·')).toBeVisible();

    const [ticket] = await printed();
    expect(await printed()).toHaveLength(1);
    expect(ticket).toContain('Mostrador');
    expect(ticket).toContain('Bebida individual');
    expect(ticket).toContain('Nota: Sin hielo');
    expect(ticket).not.toContain('$');
    await expect(page.getByRole('alert')).toHaveCount(0);

    // A reload keeps the station active and never prints the same job again.
    await page.reload();
    await expect(page.getByTestId('station-state')).toHaveText('Imprimiendo');
    await page.waitForTimeout(4000);
    expect(await printed()).toHaveLength(0);

    await page.getByRole('button', { name: 'Reimprimir' }).click();
    await expect.poll(async () => (await printed()).length).toBe(1);
    expect((await printed())[0]).toContain('REIMPRESIÓN');
    await expect(page.getByText(/reimpresión/)).toBeVisible();
});

test('la estación avisa si se pierde la sesión, la conexión o hay pendientes viejos', async ({
    page,
    context,
}) => {
    await capturePrints(page);
    await logIn(page);
    await page.goto('/admin/print-station');
    await page
        .getByRole('button', { name: 'Activar esta PC como estación' })
        .click();
    await expect(page.getByTestId('pending-count')).toHaveText('0');

    await page.route('**/admin/print-jobs/status', (route) =>
        route.fulfill({
            json: {
                data: {
                    pending: 4,
                    oldest_pending_seconds: 45,
                    unconfirmed: 1,
                    oldest_unconfirmed_seconds: 40,
                },
            },
        }),
    );
    await page.route('**/admin/print-jobs/claim', (route) =>
        route.fulfill({ json: { data: [] } }),
    );
    await expect(
        page.getByRole('alert').filter({ hasText: 'hace más de 30 segundos' }),
    ).toBeVisible();
    await expect(
        page
            .getByRole('alert')
            .filter({ hasText: 'Hay trabajos sin confirmar' }),
    ).toBeVisible();
    await expect(page.getByTestId('pending-count')).toHaveText('4');

    await page.unroute('**/admin/print-jobs/status');
    await page.route('**/admin/print-jobs/status', (route) => route.abort());
    await expect(
        page
            .getByRole('alert')
            .filter({ hasText: 'Sin conexión con el servidor' }),
    ).toBeVisible();

    await page.unroute('**/admin/print-jobs/status');
    await page.unroute('**/admin/print-jobs/claim');
    await context.clearCookies();
    const session = page
        .getByRole('alert')
        .filter({ hasText: 'Se cerró la sesión' });
    await expect(session).toBeVisible();
    await expect(
        session.getByRole('link', { name: 'Iniciar sesión' }),
    ).toHaveAttribute('href', '/login');
});

test('la estación imprime la cuenta con precio de docena sumando rondas y la leyenda', async ({
    page,
}) => {
    billTwoRoundsOfSaltenas();
    const printed = await capturePrints(page);
    await logIn(page);
    await page.goto('/admin/print-station');
    await expect(page.getByTestId('pending-count')).toHaveText('3');
    await page
        .getByRole('button', { name: 'Activar esta PC como estación' })
        .click();
    await expect(page.getByTestId('pending-count')).toHaveText('0');

    const documents = await printed();
    expect(documents).toHaveLength(3);
    const [firstRound, secondRound, bill] = documents;
    expect(firstRound).toContain('Mesa M1');
    expect(firstRound).toContain('Ronda 1');
    expect(secondRound).toContain('Ronda 2');
    expect(firstRound).not.toContain('$');
    expect(bill).toContain('12 × Salteña');
    expect(bill).toContain('1 docena × $&nbsp;18.000');
    expect(bill).toMatch(/TOTAL<\/td><td class="right">\$&nbsp;18\.000</);
    expect(bill).toContain('Documento no válido como factura');
});

test('un acuse que falla no frena el resto de la tanda y se reintenta', async ({
    page,
}) => {
    createCounterOrder();
    createCounterOrder();
    const printed = await capturePrints(page);
    await logIn(page);
    let failedAcks = 0;
    await page.route('**/admin/print-jobs/*/printed', (route) => {
        if (failedAcks === 0) {
            failedAcks += 1;

            return route.abort();
        }

        return route.continue();
    });

    await page.goto('/admin/print-station');
    await page
        .getByRole('button', { name: 'Activar esta PC como estación' })
        .click();

    await expect.poll(async () => (await printed()).length).toBe(2);
    await expect(page.getByTestId('pending-count')).toHaveText('0');
    // The stored acknowledgement is retried on the next tick: nothing stays unconfirmed.
    await expect(page.getByText('Sin confirmar ·')).toHaveCount(0);
    await expect(page.getByText('Impreso ·')).toHaveCount(2);
    expect(failedAcks).toBe(1);
});

test('un pedido colgado termina en la alerta de conexión', async ({ page }) => {
    await capturePrints(page);
    await logIn(page);
    await page.goto('/admin/print-station');
    await expect(page.getByTestId('pending-count')).toHaveText('0');

    // Never answered: the request times out instead of freezing the polling.
    await page.route('**/admin/print-jobs/status', () => undefined);
    await expect(
        page
            .getByRole('alert')
            .filter({ hasText: 'Sin conexión con el servidor' }),
    ).toBeVisible({ timeout: 20_000 });
});
