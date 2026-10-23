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

/**
 * Salón (M1 free, M2 Franco's, M3 the owner's asking for the bill, M4 inactive) and Vereda (M10 free),
 * placed as the floor editor stores them: fractions of the area.
 */
function prepareFloor(): void {
    tinker(String.raw`
        $salon = App\Modules\Restaurant\Models\DiningArea::query()->create(['name' => 'Salón', 'width' => 1600, 'height' => 1000, 'is_active' => true,
            'obstacles' => [['type' => 'rectangle', 'x' => '0.0500', 'y' => '0.8000', 'width' => '0.4000', 'height' => '0.1200', 'label' => 'Barra']]]);
        $table = fn ($area, $code, $shape, $x, $y, $w, $h, $rotation = 0, $active = true) => $area->tables()->create([
            'code' => $code, 'shape' => $shape, 'position_x' => $x, 'position_y' => $y, 'width' => $w, 'height' => $h, 'rotation' => $rotation, 'is_active' => $active,
        ]);
        $table($salon, 'M1', 'rectangle', '0.1000', '0.1000', '0.2000', '0.2000');
        $table($salon, 'M2', 'circle', '0.5000', '0.1000', '0.1250', '0.2000');
        $table($salon, 'M3', 'rectangle', '0.5000', '0.5000', '0.2000', '0.1500', 45);
        $table($salon, 'M4', 'rectangle', '0.1000', '0.4000', '0.1000', '0.1000', 0, false);
        $vereda = App\Modules\Restaurant\Models\DiningArea::query()->create(['name' => 'Vereda', 'width' => 1200, 'height' => 600, 'is_active' => true]);
        $table($vereda, 'M10', 'circle', '0.2000', '0.2000', '0.1000', '0.2000');
        $waiter = App\Modules\Identity\Models\User::factory()->withRole(App\Modules\Identity\Enums\Role::Waiter)->create([
            'name' => 'Franco', 'email' => 'playwright-waiter@example.test', 'password' => Illuminate\Support\Facades\Hash::make('playwright-password'),
        ]);
        $owner = App\Modules\Identity\Models\User::where('email', 'playwright-admin@example.test')->firstOrFail();
        $round = fn ($code, $actor) => app(App\Modules\Orders\Actions\ConfirmTableRound::class)->execute(
            diningTableId: App\Modules\Restaurant\Models\DiningTable::where('code', $code)->value('id'), expectedSessionId: null,
            kind: App\Modules\Orders\Enums\TableRoundKind::Order,
            items: [['product_id' => App\Modules\Products\Models\Product::where('slug', 'saltena')->value('id'), 'quantity' => 6]],
            addons: [], note: null, roundKey: (string) Illuminate\Support\Str::uuid(), actor: $actor,
        );
        $round('M2', $waiter);
        app(App\Modules\Orders\Actions\RequestTableBill::class)->execute($round('M3', $owner)->session, $owner);
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

/** The table's box relative to its plan, as fractions: where the editor put it. */
async function placement(page: Page, area: string, code: string) {
    const plan = await page
        .getByRole('group', { name: `Plano de ${area}` })
        .boundingBox();
    const spot = await page
        .locator(`[data-floor-table="${code}"]`)
        .evaluate((element) => {
            const { offsetLeft, offsetTop, offsetWidth, offsetHeight } =
                element as HTMLElement;

            return { offsetLeft, offsetTop, offsetWidth, offsetHeight };
        });

    return {
        x: spot.offsetLeft / plan!.width,
        y: spot.offsetTop / plan!.height,
        width: spot.offsetWidth / plan!.width,
        height: spot.offsetHeight / plan!.height,
    };
}

test('el mozo encuentra la mesa en el plano, con su estado, y la abre', async ({
    page,
}, testInfo) => {
    prepareFloor();
    await logIn(page, 'playwright-waiter@example.test');
    await page.goto('/admin/tables');
    const mobile = testInfo.project.name === 'mobile-chromium';

    // The plan is the default view.
    await expect(page.getByRole('button', { name: 'Plano' })).toHaveAttribute(
        'aria-pressed',
        'true',
    );
    const salon = page.getByRole('group', { name: 'Plano de Salón' });
    await expect(salon).toBeVisible();
    await expect(salon.getByText('Barra')).toBeVisible();

    // Each table where the editor put it, with its shape; inactive tables stay off the board.
    const m1 = await placement(page, 'Salón', 'M1');
    expect(m1.x).toBeCloseTo(0.1, 2);
    expect(m1.y).toBeCloseTo(0.1, 2);
    expect(m1.width).toBeCloseTo(0.2, 2);
    expect(m1.height).toBeCloseTo(0.2, 2);
    await expect(page.locator('[data-floor-table="M2"]')).toHaveClass(
        /rounded-full/,
    );
    await expect(page.locator('[data-floor-table="M3"]')).toHaveAttribute(
        'style',
        /rotate\(45deg\)/,
    );
    await expect(page.locator('[data-floor-table="M4"]')).toHaveCount(0);

    // State per table: free, mine, someone else's asking for the bill.
    await expect(
        salon.getByRole('link', { name: 'Mesa M1: Libre' }),
    ).toBeVisible();
    await expect(
        salon.getByRole('link', {
            name: /^Mesa M2: Ocupada, Tu mesa, \$\s10\.200$/,
        }),
    ).toBeVisible();
    await expect(
        salon.getByRole('link', {
            name: /^Mesa M3: Pide la cuenta, Playwright Administrator, \$\s10\.200$/,
        }),
    ).toBeVisible();
    // Waiter and total stay readable however small the plan draws the table.
    const occupied = page.getByRole('list', {
        name: 'Mesas ocupadas en Salón',
    });
    await expect(occupied.getByRole('listitem')).toHaveText([
        /^M2\s*Tu mesa\s*\$\s10\.200$/,
        /^M3\s*Pide la cuenta\s*Playwright Administrator\s*\$\s10\.200$/,
    ]);

    if (mobile) {
        // One tab per area; the plan fills the phone's width without scrolling sideways.
        const tabs = page.getByRole('tablist', { name: 'Áreas' });
        await expect(tabs.getByRole('tab')).toHaveText(['Salón', 'Vereda']);
        await expect(tabs.getByRole('tab', { name: 'Salón' })).toHaveAttribute(
            'aria-selected',
            'true',
        );
        await expect(
            page.getByRole('group', { name: 'Plano de Vereda' }),
        ).toBeHidden();
        const plan = await salon.boundingBox();
        const viewport = page.viewportSize()!;
        expect(plan!.width).toBeGreaterThan(viewport.width - 48);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);

        await tabs.getByRole('tab', { name: 'Vereda' }).click();
        await expect(salon).toBeHidden();
    } else {
        // Wide screens show every area at once.
        await expect(page.getByRole('tablist', { name: 'Áreas' })).toBeHidden();
    }

    const vereda = page.getByRole('group', { name: 'Plano de Vereda' });
    await expect(vereda).toBeVisible();
    expect(await placement(page, 'Vereda', 'M10')).toMatchObject({
        x: expect.closeTo(0.2, 2),
        y: expect.closeTo(0.2, 2),
    });

    // Tapping a table opens it as before.
    await vereda.getByRole('link', { name: 'Mesa M10: Libre' }).click();
    await expect(page).toHaveURL(/\/admin\/tables\/\d+$/);
    await expect(page.getByRole('heading', { name: 'Mesa M10' })).toBeVisible();
});

test('el botón Lista vuelve a la lista de mesas y Plano al plano', async ({
    page,
}) => {
    prepareFloor();
    await logIn(page, 'playwright-waiter@example.test');
    await page.goto('/admin/tables');

    await page.getByRole('button', { name: 'Lista' }).click();
    await expect(page.getByRole('button', { name: 'Lista' })).toHaveAttribute(
        'aria-pressed',
        'true',
    );
    await expect(page.getByTestId('tables-floor-plan')).toHaveCount(0);
    const card = page.getByRole('link', { name: 'Mesa M3', exact: true });
    await expect(card).toContainText('Pide la cuenta');
    await expect(card).toContainText('Playwright Administrator');
    await expect(
        page.getByRole('link', { name: 'Mesa M2', exact: true }),
    ).toContainText('Tu mesa');

    await page.getByRole('button', { name: 'Plano' }).click();
    await expect(
        page.getByRole('group', { name: 'Plano de Salón' }),
    ).toBeVisible();
    await page.getByRole('link', { name: /^Mesa M2:/ }).click();
    await expect(page.getByRole('heading', { name: 'Mesa M2' })).toBeVisible();
});
