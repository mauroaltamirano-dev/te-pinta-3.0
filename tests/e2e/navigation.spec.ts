import { expect, test } from '@playwright/test';

test('public navigation exposes the foundation pages', async ({ page }) => {
    await page.goto('/');
    await expect(
        page.getByRole('heading', { name: 'Your next order starts here.' }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Menu' }).click();
    await expect(
        page.getByRole('heading', { name: 'Menu', exact: true }),
    ).toBeVisible();

    await page.getByRole('link', { name: 'Cart', exact: true }).click();
    await expect(
        page.getByRole('heading', { name: 'Cart', exact: true }),
    ).toBeVisible();
});

test('admin redirects visitors to login', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/login$/);
    await expect(
        page.getByRole('heading', { name: 'Te Pinta administration' }),
    ).toBeVisible();
});
