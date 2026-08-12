import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
async function logIn(page: Page) {
    await page.goto('/login');
    await page
        .getByLabel('Email address')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/admin$/);
}
test('administrator creates an order and updates its lifecycle', async ({
    page,
}) => {
    await logIn(page);
    await page.goto('/admin/orders/create');
    const product = page
        .getByText('Salteña', { exact: true })
        .locator('xpath=ancestor::*[.//button[normalize-space()="Unit"]][1]');
    await product.getByRole('button', { name: 'Add one Salteña' }).click();
    await page.getByLabel('Name').fill('Playwright Customer');
    await expect(page.getByText('Price summary')).toBeVisible();
    await page.getByRole('button', { name: 'Create order' }).click();
    await expect(page).toHaveURL(/\/admin\/orders\/TP-/);
    await expect(page.getByRole('heading', { name: /TP-/ })).toBeVisible();
    await page.getByLabel('Order status').click();
    await page.getByRole('option', { name: 'Prepared' }).click();
    await expect(page.getByLabel('Order status')).toHaveText(/Prepared/);
    await page.getByLabel('Payment status').click();
    await page.getByRole('option', { name: 'Paid', exact: true }).click();
    await page.getByRole('link', { name: 'Playwright Customer' }).click();
    await expect(
        page.getByRole('heading', { name: 'Playwright Customer' }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: /TP-/ })).toBeVisible();
});
