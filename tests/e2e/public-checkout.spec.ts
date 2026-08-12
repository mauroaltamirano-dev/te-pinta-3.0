import { expect, test } from '@playwright/test';
test('public checkout persists an order before opening WhatsApp', async ({
    page,
}) => {
    await page.goto('/menu');
    const product = page
        .getByText('Salteña', { exact: true })
        .locator('xpath=ancestor::*[.//button[normalize-space()="Unit"]][1]');
    await product.getByRole('button', { name: 'Add one Salteña' }).click();
    await page.getByRole('link', { name: /View cart/ }).click();
    await page.getByLabel('Name').fill('Public Customer');
    await page.getByLabel('Phone').fill('+54 9 11 5555 0000');
    await page.route('https://wa.me/**', async (route) =>
        route.fulfill({
            status: 200,
            contentType: 'text/html',
            body: '<h1>WhatsApp intercepted</h1>',
        }),
    );
    let createdOrder: {
        order_id: string;
        total: number;
        summary: string;
    } | null = null;
    await page.route('**/checkout/orders', async (route) => {
        const response = await route.fetch();
        const payload = (await response.json()) as {
            data: typeof createdOrder;
        };
        createdOrder = payload.data;
        await route.fulfill({ response });
    });
    await page
        .getByRole('button', { name: 'Confirm and open WhatsApp' })
        .click();
    await expect.poll(() => createdOrder?.order_id).toMatch(/^TP-\d{6}$/);
    expect(createdOrder?.total).toBeGreaterThan(1);
    expect(createdOrder?.summary).toContain(createdOrder?.order_id);
    await expect(page).toHaveURL(/wa\.me/);
});
