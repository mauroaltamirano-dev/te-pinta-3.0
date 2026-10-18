import { execFileSync } from 'node:child_process';
import { expect, test } from './support/test';

// Exercise only existing admin surfaces, not a new POS or dining-table UI.
test('existing admin surfaces handle an immediate dine-in order without a customer', async ({
    page,
}) => {
    const fixture = String.raw`
        $actor = App\Modules\Identity\Models\User::where('email', 'playwright-admin@example.test')->firstOrFail();
        $product = App\Modules\Products\Models\Product::where('slug', 'playwright-unit-beverage')->firstOrFail();
        $data = new App\Modules\Orders\Data\OrderCheckoutData(
            items: [['product_id' => $product->id, 'quantity' => 2]], addons: [],
            condition: App\Modules\Orders\Enums\OrderCondition::Cooked,
            deliveryType: App\Modules\Orders\Enums\DeliveryType::DineIn,
            deliveryPeriod: null, deliveryDate: null, deliveryTime: null,
            deliveryAddress: null, notes: null, customerId: null, customerName: null,
            customerPhone: null, customerPhoneNormalized: null, customerAddress: null,
            paymentStatus: App\Modules\Orders\Enums\PaymentStatus::Unpaid,
        );
        $order = app(App\Modules\Orders\Actions\CreateOrder::class)->execute($data, App\Modules\Orders\Enums\OrderSource::Pos, $actor->id);
        echo $order->public_id;
    `;
    const output = execFileSync(
        'php',
        ['artisan', 'tinker', '--execute', fixture],
        {
            env: {
                ...process.env,
                APP_ENV: 'testing',
                DB_DATABASE:
                    process.env.PLAYWRIGHT_DB_DATABASE ?? 'testing_playwright',
                DB_URL: '',
            },
            encoding: 'utf8',
        },
    );
    const orderId = output.match(/TP-\d+/)?.[0];
    expect(orderId).toBeTruthy();
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
    await page.goto(`/admin/orders?search=${orderId}`);
    const card = page.getByTestId('order-card');
    await expect(card).toContainText('Salón · sin cliente asociado');
    await expect(card.locator('a[href^="/admin/customers/"]')).toHaveCount(0);
    await page.goto(`/admin/orders/${orderId}`);
    await expect(
        page.getByText('Salón · sin cliente asociado', { exact: true }),
    ).toBeVisible();
    await expect(page.locator('a[href^="/admin/customers/"]')).toHaveCount(0);
    await page.goto(`/admin/orders/${orderId}/edit`);
    await expect(
        page.getByText(
            'Salón · pedido inmediato, sin dirección ni programación.',
        ),
    ).toBeVisible();
    await expect(page.locator('#delivery-period')).toHaveCount(0);
    await expect(page.locator('#new-customer-name')).not.toHaveAttribute(
        'required',
    );
    await expect(page.locator('#new-customer-address')).toHaveCount(0);
    expect(errors).toEqual([]);
});
