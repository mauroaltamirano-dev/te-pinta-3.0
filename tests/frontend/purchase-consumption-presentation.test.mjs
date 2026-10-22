import assert from 'node:assert/strict';
import test from 'node:test';
import {
    consumptionExample,
    inventoryItemKindLabel,
    multiplyDecimal,
} from '../../resources/js/features/inventory/presentation.ts';
import { purchaseLineSubtotal } from '../../resources/js/features/purchases/presentation.ts';

test('a line priced in total keeps the entered amount', () => {
    assert.equal(purchaseLineSubtotal('2', 'total', '4300'), 4300);
    assert.equal(purchaseLineSubtotal('', 'total', '4300'), 4300);
});

test('a line priced per unit multiplies like the backend', () => {
    assert.equal(purchaseLineSubtotal('2', 'unit', '4300'), 8600);
    assert.equal(purchaseLineSubtotal('0.5', 'unit', '1200'), 600);
    assert.equal(purchaseLineSubtotal('1.25', 'unit', '1000'), 1250);
});

test('a per-unit total with centavos is flagged, never rounded', () => {
    assert.equal(purchaseLineSubtotal('0.333', 'unit', '100'), 'fractional');
});

test('incomplete or invalid lines have no subtotal', () => {
    assert.equal(purchaseLineSubtotal('2', 'unit', ''), null);
    assert.equal(purchaseLineSubtotal('', 'unit', '4300'), null);
    assert.equal(purchaseLineSubtotal('2', 'total', '43,5'), null);
    assert.equal(purchaseLineSubtotal('2,5', 'unit', '100'), null);
});

test('item classifications read in Spanish', () => {
    assert.equal(inventoryItemKindLabel('semi_finished'), 'Semielaborado');
    assert.equal(inventoryItemKindLabel('raw_material'), 'Materia prima');
    assert.equal(inventoryItemKindLabel(null), 'Clasificación pendiente');
});

test('decimal multiplication is exact', () => {
    assert.equal(multiplyDecimal('60', 12n), '720');
    assert.equal(multiplyDecimal('0.055', 12n), '0.66');
    assert.equal(multiplyDecimal('2.5', 6n), '15');
    assert.equal(multiplyDecimal('abc', 12n), null);
});

test('the consumption example multiplies one sold unit by a dozen', () => {
    assert.equal(
        consumptionExample('60', 'g'),
        'Una docena descuenta 12 × 60 g = 720 g',
    );
    assert.equal(
        consumptionExample('1', 'unit'),
        'Una docena descuenta 12 × 1 unidad = 12 unidades',
    );
    assert.equal(
        consumptionExample('0.055', 'kg'),
        'Una docena descuenta 12 × 0,055 kg = 0,66 kg',
    );
    assert.equal(consumptionExample('', 'g'), null);
});
