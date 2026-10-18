import { expect, test } from './support/test';

test.describe.serial('Restaurant light theme', () => {
    test.beforeEach(async ({ page }) => {
        await page.emulateMedia({ colorScheme: 'dark' });
        await page.addInitScript(() =>
            localStorage.setItem('appearance', 'dark'),
        );
        await page.goto('/login');
        await page
            .getByLabel('Correo electrónico')
            .fill('playwright-admin@example.test');
        await page.locator('#password').fill('playwright-password');
        await page
            .getByRole('button', { name: 'Iniciar sesión', exact: true })
            .click();
        await expect(page).toHaveURL(/\/admin$/);
    });

    test('stays light despite saved and system dark preferences', async ({
        page,
    }) => {
        await expect(page.locator('html')).not.toHaveClass(/dark/);
        await expect(page.locator('.admin-shell').first()).not.toHaveClass(
            /dark/,
        );
        await expect(page.locator('.admin-shell').first()).toHaveCSS(
            'background-color',
            'rgb(247, 248, 250)',
        );
        await expect(page.locator('.admin-shell').first()).toHaveCSS(
            'font-family',
            /Barlow/,
        );
        await page.screenshot({
            path: `storage/app/light-theme-home-${test.info().project.name}.png`,
            fullPage: true,
        });
    });

    test('user management uses readable light-theme text', async ({ page }) => {
        await page.goto('/admin/users');
        const heading = page.getByRole('heading', {
            name: 'Usuarios',
            exact: true,
        });
        const memberName = page.getByText('Playwright Administrator', {
            exact: true,
        });
        await expect(heading).toBeVisible();
        await expect(memberName).toBeVisible();

        const primaryText = await page.evaluate(() => {
            const probe = document.createElement('span');
            probe.style.color = 'var(--admin-text-primary)';
            document.body.append(probe);
            const color = getComputedStyle(probe).color;
            probe.remove();

            return color;
        });
        await expect(heading).toHaveCSS('color', primaryText);
        await expect(memberName).toHaveCSS('color', primaryText);
    });

    test('badges remain readable and use the same status labels', async ({
        page,
    }) => {
        await page.goto('/admin/orders');
        // Open the public status control rather than inspecting a copied palette fixture.
        const status = page
            .getByRole('combobox', { name: /^Estado de / })
            .first();
        await expect(status).toBeVisible();
        await status.click();

        for (const label of [
            'En espera',
            'Confirmado',
            'Preparado',
            'Entregado',
            'Cancelado',
        ]) {
            const option = page.getByRole('option', {
                name: label,
                exact: true,
            });
            await expect(option).toBeVisible();
            const ratio = await option
                .locator('span.rounded-md')
                .evaluate((element) => {
                    const style = getComputedStyle(element);
                    const luminance = (color: string) =>
                        color
                            .match(/[\d.]+/g)!
                            .slice(0, 3)
                            .map(Number)
                            .map((channel) => {
                                const value = channel / 255;

                                return value <= 0.04045
                                    ? value / 12.92
                                    : ((value + 0.055) / 1.055) ** 2.4;
                            })
                            .reduce(
                                (sum, value, index) =>
                                    sum +
                                    value * [0.2126, 0.7152, 0.0722][index],
                                0,
                            );
                    const first = luminance(style.color);
                    const second = luminance(style.backgroundColor);

                    return (
                        (Math.max(first, second) + 0.05) /
                        (Math.min(first, second) + 0.05)
                    );
                });
            expect(ratio, label).toBeGreaterThanOrEqual(4.5);
        }

        await page.keyboard.press('Escape');
    });

    test('previews locally, saves without a rebuild and rejects unreadable colors', async ({
        page,
    }) => {
        await page.goto('/admin/settings/general');
        const input = page.getByLabel('Color de acento', { exact: true });
        await expect(input).toHaveValue('#b84f3f');
        await input.fill('#1f6f4a');
        await expect(page.getByTestId('branding-preview-action')).toHaveCSS(
            'background-color',
            'rgb(31, 111, 74)',
        );
        await expect(page.locator('html')).toHaveCSS(
            '--brand-accent',
            '#b84f3f',
        );
        await page
            .getByRole('button', {
                name: 'Guardar color de acento',
                exact: true,
            })
            .click();
        await expect(page.locator('html')).toHaveCSS(
            '--brand-accent',
            '#1f6f4a',
        );
        await page.reload();
        await expect(input).toHaveValue('#1f6f4a');
        await page.goto('/menu');
        await expect(page.locator('.public-shell')).toHaveCSS(
            'background-color',
            'rgb(31, 111, 74)',
        );
        await page.goto('/admin/settings/general');
        await input.fill('#ffe066');
        await page
            .getByRole('button', {
                name: 'Guardar color de acento',
                exact: true,
            })
            .click();
        await expect(
            page.getByText(/El color de acento no se distingue/),
        ).toBeVisible();
        await expect(input).toHaveAttribute('aria-invalid', 'true');
        await expect(page.locator('html')).toHaveCSS(
            '--brand-accent',
            '#1f6f4a',
        );
        await input.fill('#b84f3f');
        await page
            .getByRole('button', {
                name: 'Guardar color de acento',
                exact: true,
            })
            .click();
        await expect(page.locator('html')).toHaveCSS(
            '--brand-accent',
            '#b84f3f',
        );
    });

    test('primary actions darken on hover to preserve label contrast', async ({
        page,
        isMobile,
    }) => {
        test.skip(isMobile, 'Touch devices do not expose a hover state.');
        await page.goto('/admin/settings/general');
        const save = page.getByRole('button', {
            name: 'Guardar color de acento',
            exact: true,
        });
        await save.hover();
        await expect(save).toHaveCSS('background-color', 'rgb(156, 67, 54)');
        await expect(save).toHaveCSS('color', 'rgb(255, 255, 255)');
    });
});
