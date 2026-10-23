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

/** Clásicas > Saladas (salada, jamón y queso, caprese) and Dulces (criolla dulce). */
function seedClassicSubcategories(): void {
    tinker(String.raw`
        $category = App\Modules\Products\Models\Category::where('slug', 'clasicas')->firstOrFail();
        $salty = App\Modules\Products\Models\ProductSubcategory::create(['category_id' => $category->id, 'name' => 'Saladas', 'slug' => 'saladas', 'sort_order' => 1, 'is_active' => true]);
        $sweet = App\Modules\Products\Models\ProductSubcategory::create(['category_id' => $category->id, 'name' => 'Dulces', 'slug' => 'dulces', 'sort_order' => 2, 'is_active' => true]);
        App\Modules\Products\Models\Product::whereIn('slug', ['criolla-salada', 'jamon-y-queso', 'caprese'])->update(['subcategory_id' => $salty->id]);
        App\Modules\Products\Models\Product::where('slug', 'criolla-dulce')->update(['subcategory_id' => $sweet->id]);
    `);
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

test('el catálogo crea subcategorías, asigna un producto y filtra por ellas', async ({
    page,
}) => {
    await logIn(page);
    await page.goto('/admin/settings/products');

    await page.getByRole('button', { name: 'Gestionar categorías' }).click();
    const dialog = page.getByRole('dialog', { name: 'Gestionar categorías' });

    for (const name of ['Saladas', 'Dulces']) {
        const create = dialog.getByRole('form', {
            name: 'Agregar subcategoría a Clásicas',
        });
        await create.getByLabel('Nombre de la subcategoría').fill(name);
        await create
            .getByRole('button', { name: 'Agregar subcategoría' })
            .click();
        await expect(
            dialog.getByRole('form', { name: `Subcategoría ${name}` }),
        ).toBeVisible();
    }

    // The same name is rejected inside one category.
    const duplicate = dialog.getByRole('form', {
        name: 'Agregar subcategoría a Clásicas',
    });
    await duplicate.getByLabel('Nombre de la subcategoría').fill('dulces');
    await duplicate
        .getByRole('button', { name: 'Agregar subcategoría' })
        .click();
    await expect(
        duplicate.getByText(
            'Ya existe una subcategoría con ese nombre en esta categoría.',
        ),
    ).toBeVisible();
    await page.keyboard.press('Escape');

    const subcategories = page.getByRole('group', { name: 'Subcategorías' });
    await expect(
        subcategories.getByRole('button', { name: /Sin subcategoría/ }),
    ).toBeVisible();

    const card = page.locator('[data-slot="card"]').filter({
        has: page.getByRole('heading', { name: 'Criolla Dulce', exact: true }),
    });
    await card.getByRole('button', { name: 'Editar producto' }).click();
    const editor = page.getByRole('dialog', { name: 'Editar producto' });
    await editor.getByLabel('Subcategoría (opcional)').click();
    await page.getByRole('option', { name: 'Dulces' }).click();
    await editor.getByRole('button', { name: 'Guardar cambios' }).click();
    await expect(editor).toBeHidden();
    await expect(card).toContainText('Clásicas › Dulces');

    await subcategories.getByRole('button', { name: 'Dulces' }).click();
    const headings = page.locator('[data-slot="card"] h3');
    await expect(headings).toHaveText(['Criolla Dulce']);

    await subcategories
        .getByRole('button', { name: /Sin subcategoría/ })
        .click();
    await expect(headings).toHaveText([
        'Criolla Salada',
        'Jamón y Queso',
        'Caprese',
    ]);

    // A search spans every category.
    await page.getByLabel('Buscar producto').fill('salteña');
    await expect(headings).toHaveText(['Salteña']);
});

test('el menú público filtra por subcategoría dentro de una categoría', async ({
    page,
}) => {
    seedClassicSubcategories();
    await page.goto('/menu');
    const cards = page.getByTestId('public-product-card');
    await expect(cards.first()).toBeVisible();
    const subcategories = page.getByRole('group', {
        name: 'Subcategorías del menú',
    });
    await expect(subcategories).toHaveCount(0);

    await page
        .getByRole('group', { name: 'Categorías del menú' })
        .getByRole('button', { name: 'Clásicas', exact: true })
        .click();
    await expect(subcategories.getByRole('button')).toHaveText([
        'Todas',
        'Saladas',
        'Dulces',
    ]);
    await expect(cards).toHaveCount(4);

    await subcategories.getByRole('button', { name: 'Dulces' }).click();
    await expect(cards).toHaveCount(1);
    await expect(cards.first()).toContainText('Criolla Dulce');
    await expect(cards.first()).toContainText('Clásicas · Dulces');

    await subcategories.getByRole('button', { name: 'Todas' }).click();
    await expect(cards).toHaveCount(4);

    await page
        .getByRole('group', { name: 'Categorías del menú' })
        .getByRole('button', { name: 'Especiales', exact: true })
        .click();
    await expect(subcategories).toHaveCount(0);
});

test('Mostrador filtra por subcategoría y arma el pedido igual', async ({
    page,
}) => {
    seedClassicSubcategories();
    await logIn(page);

    await page
        .getByRole('navigation', { name: 'Categorías de productos' })
        .getByRole('button', { name: 'Clásicas', exact: true })
        .click();
    const subcategories = page.getByRole('group', { name: 'Subcategorías' });
    await expect(subcategories.getByRole('button')).toHaveText([
        'Todas',
        'Saladas',
        'Dulces',
    ]);

    await subcategories.getByRole('button', { name: 'Dulces' }).click();
    const addOne = page.getByRole('button', { name: /^Agregar 1 unidad de / });
    await expect(addOne).toHaveCount(1);
    await page
        .getByRole('button', { name: 'Agregar 12 unidades de Criolla Dulce' })
        .click();

    await subcategories.getByRole('button', { name: 'Saladas' }).click();
    await expect(addOne).toHaveCount(3);

    await page
        .getByRole('navigation', { name: 'Categorías de productos' })
        .getByRole('button', { name: 'Todas', exact: true })
        .click();
    await expect(subcategories).toHaveCount(0);
});

test('las rondas de mesa filtran por subcategoría', async ({ page }) => {
    seedClassicSubcategories();
    const tableId = tinker(String.raw`
        echo App\Modules\Restaurant\Models\DiningArea::query()->create(['name' => 'Salón', 'width' => 1600, 'height' => 1000, 'is_active' => true])
            ->tables()->create(['code' => 'M1', 'shape' => 'rectangle', 'position_x' => '0.1000', 'position_y' => '0.1000', 'width' => '0.1000', 'height' => '0.1000', 'rotation' => 0, 'is_active' => true])->id;
    `);
    await logIn(page);
    await page.goto(`/admin/tables/${tableId}`);

    const composer = page.getByRole('region', { name: 'Nueva ronda' });
    await composer
        .getByRole('navigation', { name: 'Categorías' })
        .getByRole('button', { name: 'Clásicas', exact: true })
        .click();
    const subcategories = composer.getByRole('group', {
        name: 'Subcategorías',
    });
    await subcategories.getByRole('button', { name: 'Saladas' }).click();

    const rows = composer.getByRole('listitem');
    await expect(rows).toHaveCount(3);
    await expect(rows.first()).toContainText('Clásicas · Saladas');

    await composer.getByLabel('Buscar producto').fill('caprese');
    await expect(rows).toHaveCount(1);
    await composer.getByRole('button', { name: 'Agregar 1 Caprese' }).click();
    await expect(
        composer.getByRole('button', { name: 'Revisar ronda 1 (1)' }),
    ).toBeVisible();
});
