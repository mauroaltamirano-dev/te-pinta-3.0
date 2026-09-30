import { expect, test } from '@playwright/test';

test('Admin Product metadata reaches Home, menu, detail and cart through the real catalog', async ({
    page,
}, testInfo) => {
    const shortCopy = `Public copy ${testInfo.project.name}`;
    const longCopy = `Editorial description ${testInfo.project.name}`;
    const ingredients = 'Carne, cebolla, papa y especias.';
    await page.goto('/login');
    await page
        .getByLabel('Correo electrónico')
        .fill('playwright-admin@example.test');
    await page.locator('#password').fill('playwright-password');
    await page
        .getByRole('button', { name: 'Iniciar sesión', exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin$/);
    await page.goto('/admin/settings/products');
    await page.getByRole('button', { name: 'Especiales' }).click();
    const card = page.locator('[data-slot="card"]').filter({
        has: page.getByRole('heading', { name: 'Salteña', exact: true }),
    });
    await card.getByRole('button', { name: 'Editar producto' }).click();
    const editor = page.getByRole('dialog', { name: 'Editar producto' });
    const original = {
        short: await editor.getByLabel('Descripción breve').inputValue(),
        long: await editor
            .getByLabel('Descripción completa para la web')
            .inputValue(),
        ingredients: await editor
            .getByLabel('Ingredientes visibles en la web')
            .inputValue(),
        vegetarian: await editor
            .getByRole('checkbox', { name: 'Vegetariana' })
            .isChecked(),
    };
    // This isolated seeded Product has no managed image to destroy or restore.
    await expect(
        editor.getByAltText('Vista previa de la imagen del producto'),
    ).toHaveCount(0);
    await editor.getByRole('button', { name: 'Cancelar' }).click();
    await card.getByRole('button', { name: 'Destacar' }).click();
    const scheduler = page.getByTestId('product-featured-scheduler');
    await scheduler
        .getByRole('button', { name: 'Editar recomendación de Salteña' })
        .click();
    const originalHeadline = await page
        .getByLabel('Título destacado')
        .inputValue();
    const originalCopy = await page.getByLabel('Texto corto').inputValue();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Cancelar' })
        .click();
    await card.getByRole('button', { name: 'Editar producto' }).click();

    try {
        await editor.getByLabel('Descripción breve').fill(shortCopy);
        await editor
            .getByLabel('Descripción completa para la web')
            .fill(longCopy);
        await editor
            .getByLabel('Ingredientes visibles en la web')
            .fill(ingredients);
        await editor.getByRole('checkbox', { name: 'Vegetariana' }).check();
        await editor.locator('#product-image').setInputFiles({
            name: 'editorial-product.png',
            mimeType: 'image/png',
            buffer: Buffer.from(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==',
                'base64',
            ),
        });
        await editor.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect(editor).toBeHidden();

        await card.getByRole('button', { name: 'Destacar' }).click();
        await scheduler
            .getByRole('button', { name: 'Editar recomendación de Salteña' })
            .click();
        await page
            .getByLabel('Título destacado')
            .fill(`Featured ${testInfo.project.name}`);
        await page.getByLabel('Texto corto').fill('');
        await page
            .getByRole('button', { name: 'Guardar recomendación' })
            .click();
        await expect(page.getByRole('dialog')).toHaveCount(0);

        await page.goto('/');
        const featured = page.getByTestId('featured-product-card');
        await expect(featured).toContainText(shortCopy);
        await expect(featured).toContainText(
            `Featured ${testInfo.project.name}`,
        );
        await expect(
            featured.getByRole('img', { name: 'Salteña', exact: true }),
        ).toHaveAttribute('src', /\/storage\/products\//);

        await page.goto('/menu');
        const menuCard = page.locator('article').filter({
            has: page.getByRole('heading', { name: 'Salteña', exact: true }),
        });
        await expect(menuCard).toContainText(shortCopy);
        await expect(menuCard).toContainText('Vegetariana');
        await page.goto('/menu/saltena');
        await expect(page.getByRole('heading', { level: 1 })).toContainText(
            'Salteña',
        );
        await expect(page.getByText(longCopy, { exact: true })).toBeVisible();
        await expect(
            page.getByText(ingredients, { exact: true }),
        ).toBeVisible();
        await page
            .getByRole('button', { name: 'Agregar 1 al carrito', exact: true })
            .first()
            .click();
        await page.goto('/cart');
        await expect(page.getByText('Salteña', { exact: true })).toBeVisible();
        await expect(page.getByLabel('Cantidad')).toHaveText('1');
        await expect(page.getByText(/Cotizando/)).toHaveCount(0);
    } finally {
        // Restore shared seeded records even if an acceptance assertion fails.
        await page.goto('/admin/settings/products');
        await page.getByRole('button', { name: 'Especiales' }).click();
        await card.getByRole('button', { name: 'Editar producto' }).click();
        await editor.getByLabel('Descripción breve').fill(original.short);
        await editor
            .getByLabel('Descripción completa para la web')
            .fill(original.long);
        await editor
            .getByLabel('Ingredientes visibles en la web')
            .fill(original.ingredients);
        await editor
            .getByRole('checkbox', { name: 'Vegetariana' })
            .setChecked(original.vegetarian);
        const removeImage = editor.getByRole('button', {
            name: 'Quitar imagen',
            exact: true,
        });

        if (await removeImage.count()) {
            await removeImage.click();
        }

        await editor.getByRole('button', { name: 'Guardar cambios' }).click();
        await expect(editor).toBeHidden();
        await card.getByRole('button', { name: 'Destacar' }).click();
        await scheduler
            .getByRole('button', { name: 'Editar recomendación de Salteña' })
            .click();
        await page.getByLabel('Título destacado').fill(originalHeadline);
        await page.getByLabel('Texto corto').fill(originalCopy);
        await page
            .getByRole('button', { name: 'Guardar recomendación' })
            .click();
        await expect(page.getByRole('dialog')).toHaveCount(0);
    }
});
