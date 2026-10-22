import assert from 'node:assert/strict';
import test from 'node:test';
import {
    editableDecimal,
    formatInventoryQuantity,
    inventoryUnitLabel,
    inventoryUnitName,
} from '../../resources/js/features/inventory/presentation.ts';
import { formatCost as formatRecipeMoney } from '../../resources/js/lib/format.ts';
test('recipe money is integer pesos, never cents', () => {
    assert.equal(formatRecipeMoney(16650).replace(/\s/g, ''), '$16.650');
    assert.equal(formatRecipeMoney(8325).replace(/\s/g, ''), '$8.325');
    assert.equal(formatRecipeMoney(null), 'Sin costo conocido');
    assert.equal(formatRecipeMoney(0).replace(/\s/g, ''), '$0');
});
test('display formatting preserves exact quantity magnitudes', () => {
    assert.equal(formatInventoryQuantity('2.500000', 'kg'), '2,5 kg');
    assert.equal(formatInventoryQuantity('150.000000', 'g'), '150 g');
    assert.equal(formatInventoryQuantity('6.000000', 'unit'), '6 unidades');
    assert.equal(
        formatInventoryQuantity('10000000000000.123456', 'g'),
        '10.000.000.000.000,123456 g',
    );
});
test('stored quantities prefill without trailing zeros and keep a dot separator', () => {
    assert.equal(editableDecimal('100.000000'), '100');
    assert.equal(editableDecimal('1.500000'), '1.5');
    assert.equal(editableDecimal('0.000000'), '0');
    assert.equal(editableDecimal('1000.000100'), '1000.0001');
    assert.equal(
        editableDecimal('10000000000000.123456'),
        '10000000000000.123456',
    );
    assert.equal(editableDecimal('12'), '12');
    assert.equal(editableDecimal(''), '');
});
test('unit options read in Spanish', () => {
    assert.equal(inventoryUnitName({ code: 'unit', name: 'Unit' }), 'Unidad');
    assert.equal(
        inventoryUnitName({ code: 'half_dozen', name: 'Half dozen' }),
        'Media docena',
    );
    assert.equal(
        inventoryUnitName({ code: 'kg', name: 'Kilogram' }),
        'Kilogramo',
    );
});
test('count units read in Spanish by code, never by stored English symbol', () => {
    assert.equal(
        formatInventoryQuantity('1.000000', { code: 'dozen', symbol: 'dozen' }),
        '1 docena',
    );
    assert.equal(formatInventoryQuantity('2.000000', 'dozen'), '2 docenas');
    assert.equal(
        formatInventoryQuantity('1.000000', {
            code: 'half_dozen',
            symbol: 'half dozen',
        }),
        '1 media docena',
    );
    assert.equal(
        formatInventoryQuantity('3.000000', 'half_dozen'),
        '3 medias docenas',
    );
    assert.equal(
        inventoryUnitLabel('1', { code: 'unit', symbol: 'unit' }),
        'unidad',
    );
    assert.equal(inventoryUnitLabel('1', { code: 'kg', symbol: 'kg' }), 'kg');
    assert.equal(inventoryUnitLabel('1', 'L'), 'L');
    assert.equal(inventoryUnitName({ code: 'g', name: 'Gram' }), 'Gramo');
    assert.equal(
        inventoryUnitName({ code: 'ml', name: 'Milliliter' }),
        'Mililitro',
    );
    assert.equal(inventoryUnitName({ code: 'dozen', name: 'Dozen' }), 'Docena');
});
