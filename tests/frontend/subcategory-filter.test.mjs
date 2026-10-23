import assert from 'node:assert/strict';
import test from 'node:test';
import {
    filterBySubcategory,
    subcategoriesOf,
} from '../../resources/js/features/products/subcategory-filter.ts';

const classics = { key: 'clasicas', name: 'Clásicas', sort_order: 1 };
const specials = { key: 'especiales', name: 'Especiales', sort_order: 2 };
const products = [
    { name: 'Cordero', subcategory: specials },
    { name: 'Humita', subcategory: null },
    { name: 'Carne', subcategory: classics },
    { name: 'Pollo', subcategory: classics },
];
const keyOf = (product) => product.subcategory;

test('lists each subcategory with products once, in its manual order', () => {
    assert.deepEqual(subcategoriesOf(products, keyOf), [classics, specials]);
});

test('ties in manual order fall back to the name', () => {
    const sameOrder = { ...specials, sort_order: 1 };

    assert.deepEqual(
        subcategoriesOf(
            [{ subcategory: sameOrder }, { subcategory: classics }],
            keyOf,
        ),
        [classics, sameOrder],
    );
});

test('a category without subcategories offers no filter', () => {
    assert.deepEqual(
        subcategoriesOf([{ subcategory: null }, { subcategory: null }], keyOf),
        [],
    );
});

test('"all" keeps every product, including those without a subcategory', () => {
    assert.deepEqual(
        filterBySubcategory(products, keyOf, 'all').map(({ name }) => name),
        ['Cordero', 'Humita', 'Carne', 'Pollo'],
    );
});

test('a subcategory keeps only its products, in their original order', () => {
    assert.deepEqual(
        filterBySubcategory(products, keyOf, 'clasicas').map(
            ({ name }) => name,
        ),
        ['Carne', 'Pollo'],
    );
});

test('a subcategory that no longer has products shows them all', () => {
    assert.deepEqual(
        filterBySubcategory(products, keyOf, 'retired').map(({ name }) => name),
        ['Cordero', 'Humita', 'Carne', 'Pollo'],
    );
});
