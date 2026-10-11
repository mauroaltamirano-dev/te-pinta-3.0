import assert from 'node:assert/strict';
import test from 'node:test';
import { formatInventoryQuantity } from '../../resources/js/features/inventory/presentation.ts';
import { formatRecipeMoney } from '../../resources/js/features/inventory/recipes/presentation.ts';
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
