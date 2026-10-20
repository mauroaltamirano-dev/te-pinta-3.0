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

/** Table M1 on the Vereda, a waiter named Franco, and the salteña dozen price. */
function prepareFloor(): number {
    return Number(
        tinker(String.raw`
            App\Modules\Restaurant\Models\DiningArea::query()->create(['name' => 'Vereda', 'width' => 1600, 'height' => 1000, 'is_active' => true])
                ->tables()->create(['code' => 'M1', 'shape' => 'rectangle', 'position_x' => '0.1000', 'position_y' => '0.1000', 'width' => '0.1000', 'height' => '0.1000', 'rotation' => 0, 'is_active' => true]);
            App\Modules\Identity\Models\User::factory()->withRole(App\Modules\Identity\Enums\Role::Waiter)->create([
                'name' => 'Franco', 'email' => 'playwright-waiter@example.test', 'password' => Illuminate\Support\Facades\Hash::make('playwright-password'),
            ]);
            echo App\Modules\Products\Models\Product::where('slug', 'saltena')->value('dozen_price');
        `),
    );
}

function configureSaltenaConsumption(): void {
    tinker(String.raw`
        $owner = App\Modules\Identity\Models\User::where('email', 'playwright-admin@example.test')->firstOrFail();
        app(App\Modules\Inventory\Actions\ConfigureProductInventoryConsumption::class)
            ->execute(App\Modules\Products\Models\Product::where('slug', 'saltena')->value('id'), 'no_inventory', [], $owner->id);
    `);
}

async function logIn(page: Page, email: string) {
    await page.goto('/login');
    await page.getByLabel('Correo electrónico').fill(email);
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
}

async function confirmRound(page: Page, label: RegExp) {
    await page.getByRole('button', { name: label }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Confirmar y enviar a cocina' })
        .click();
}

const money = (amount: number) =>
    new RegExp(`\\$\\s${amount.toLocaleString('es-AR')}$`);

test.beforeEach(({ page }, testInfo) => {
    void page;
    test.skip(
        testInfo.project.name !== 'mobile-chromium',
        'El mozo atiende desde el celular.',
    );
});

test('el mozo abre la mesa, comanda dos rondas de 6 salteñas y caja cobra la docena', async ({
    page,
    context,
}) => {
    const dozen = prepareFloor();
    await logIn(page, 'playwright-waiter@example.test');

    // The POS home leads to the tables.
    await page.getByRole('link', { name: 'Mesas', exact: true }).click();
    await expect(page).toHaveURL(/\/admin\/tables$/);
    await page.getByRole('link', { name: 'Mesa M1' }).click();
    await expect(page.getByRole('heading', { name: 'Mesa M1' })).toBeVisible();

    await page.getByRole('button', { name: 'Agregar 6 Salteña' }).click();
    await confirmRound(page, /Revisar ronda 1 \(6\)/);
    await expect(
        page.getByText('Ronda 1 confirmada y enviada a cocina.'),
    ).toBeVisible();
    await expect(page.getByLabel('Cantidad de Salteña')).toHaveText('0');

    await page.getByRole('button', { name: 'Agregar 6 Salteña' }).click();
    await page.getByLabel('Nota para cocina').fill('Bien cocidas');
    await confirmRound(page, /Revisar ronda 2 \(6\)/);
    await expect(page.getByTestId('table-total')).toHaveText(money(dozen));
    await expect(
        page.getByRole('region', { name: 'Total provisorio' }),
    ).toContainText('12 × Salteña');
    await expect(page.getByText('Nota: Bien cocidas')).toBeVisible();

    await page.getByRole('button', { name: 'Pedir la cuenta' }).click();
    await expect(page.getByText('La mesa pidió la cuenta.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cobrar' })).toHaveCount(0);

    await context.clearCookies();
    await logIn(page, 'playwright-admin@example.test');
    await page.goto('/admin/tables');
    const card = page.getByRole('link', { name: 'Mesa M1' });
    await expect(card).toContainText('Pide la cuenta');
    await expect(card).toContainText('Franco');
    await card.click();

    await page.getByRole('button', { name: 'Imprimir cuenta' }).click();
    await expect(
        page.getByText('Cuenta enviada a la impresora.'),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Cobrar' }).click();
    const charge = page.getByRole('dialog');
    await expect(charge.getByRole('alert')).toContainText('Salteña');
    await charge.getByRole('button', { name: 'Volver' }).click();
    configureSaltenaConsumption();
    await page.reload();

    await page.getByRole('button', { name: 'Cobrar' }).click();
    await expect(charge.getByRole('alert')).toHaveCount(0);
    await expect(charge.getByText(money(dozen))).toBeVisible();
    await charge.getByRole('button', { name: 'Efectivo' }).click();
    await charge.getByRole('button', { name: 'Confirmar cobro' }).click();
    await expect(page).toHaveURL(/\/admin\/tables$/);
    await expect(page.getByText('Mesa cobrada y cerrada.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Mesa M1' })).toContainText(
        'Libre',
    );
});

test('una mesa de otro mozo se ve pero no se carga', async ({ page }) => {
    prepareFloor();
    tinker(String.raw`
        $owner = App\Modules\Identity\Models\User::where('email', 'playwright-admin@example.test')->firstOrFail();
        app(App\Modules\Orders\Actions\ConfirmTableRound::class)->execute(
            diningTableId: App\Modules\Restaurant\Models\DiningTable::where('code', 'M1')->value('id'), expectedSessionId: null,
            kind: App\Modules\Orders\Enums\TableRoundKind::Order,
            items: [['product_id' => App\Modules\Products\Models\Product::where('slug', 'saltena')->value('id'), 'quantity' => 3]],
            addons: [], note: null, roundKey: (string) Illuminate\Support\Str::uuid(), actor: $owner,
        );
    `);
    await logIn(page, 'playwright-waiter@example.test');
    await page.goto('/admin/tables');
    await page.getByRole('link', { name: 'Mesa M1' }).click();

    await expect(
        page.getByText('Esta mesa la atiende Playwright Administrator.'),
    ).toBeVisible();
    await expect(page.getByRole('region', { name: 'Nueva ronda' })).toHaveCount(
        0,
    );
    await expect(
        page.getByRole('button', { name: 'Pedir la cuenta' }),
    ).toHaveCount(0);
});

test('reintentar una ronda cuya respuesta se perdió no la comanda dos veces', async ({
    page,
}) => {
    prepareFloor();
    await logIn(page, 'playwright-waiter@example.test');
    await page.goto('/admin/tables');
    await page.getByRole('link', { name: 'Mesa M1' }).click();
    let lost = false;
    await page.route('**/admin/tables/*/rounds', async (route) => {
        if (lost) {
            return route.continue();
        }

        // The server confirms the round, but the phone never gets the answer.
        lost = true;
        await route.fetch({ maxRedirects: 0 });

        return route.abort();
    });

    await page.getByRole('button', { name: 'Agregar 6 Salteña' }).click();
    await confirmRound(page, /Revisar ronda 1 \(6\)/);
    await expect(
        page.getByText('Sin conexión con el servidor: no se guardó.'),
    ).toBeVisible();
    await confirmRound(page, /Revisar ronda 1 \(6\)/);

    await expect(
        page.getByRole('region', { name: 'Total provisorio' }),
    ).toContainText('6 × Salteña');
    await expect(
        page.getByRole('region', { name: 'Rondas' }).getByRole('article'),
    ).toHaveCount(1);
    expect(
        tinker(
            'echo App\\Modules\\Printing\\Models\\PrintJob::query()->where("kind", "kitchen_ticket")->count();',
        ),
    ).toBe('1');
});

test('una mesa cobrada en caja saca al mozo del modo anular', async ({
    page,
}) => {
    prepareFloor();
    configureSaltenaConsumption();
    await logIn(page, 'playwright-waiter@example.test');
    await page.goto('/admin/tables');
    await page.getByRole('link', { name: 'Mesa M1' }).click();
    await page.getByRole('button', { name: 'Agregar 6 Salteña' }).click();
    await confirmRound(page, /Revisar ronda 1 \(6\)/);
    await expect(page.getByTestId('table-total')).toBeVisible();

    await page.getByRole('button', { name: 'Anular' }).click();
    await page.getByRole('button', { name: 'Agregar 1 Salteña' }).click();
    tinker(String.raw`
        $owner = App\Modules\Identity\Models\User::where('email', 'playwright-admin@example.test')->firstOrFail();
        app(App\Modules\Orders\Actions\ChargeTableSession::class)->execute(
            App\Modules\Orders\Models\TableSession::query()->sole(), App\Modules\Orders\Enums\PaymentMethod::Cash, $owner,
        );
    `);

    // The next poll shows the free table, ready for a new order with an empty draft.
    await expect(page.getByTestId('table-total')).toHaveCount(0, {
        timeout: 15_000,
    });
    await expect(
        page.getByRole('heading', { name: 'Nueva ronda' }),
    ).toBeVisible();
    await expect(page.getByLabel('Cantidad de Salteña')).toHaveText('0');
    await expect(
        page.getByRole('button', { name: 'Elegí productos' }),
    ).toBeDisabled();
});
