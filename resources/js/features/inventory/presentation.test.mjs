import assert from 'node:assert/strict';
import test from 'node:test';
import {
    formatDecimalQuantity,
    formatInventoryQuantity,
} from './presentation.ts';

test('NUMERIC quantities retain every significant digit', () => {
    assert.equal(
        formatDecimalQuantity('99999999999999.000001'),
        '99.999.999.999.999,000001',
    );
    assert.equal(formatDecimalQuantity('-0.000000'), '0');
    assert.equal(formatDecimalQuantity('1000.125000'), '1.000,125');
    assert.equal(formatDecimalQuantity('invalid'), 'invalid');
});
test('physical units are readable without converting quantities to Number', () => {
    assert.equal(formatInventoryQuantity('1.000000', 'unit'), '1 unidad');
    assert.equal(formatInventoryQuantity('12.000000', 'unit'), '12 unidades');
    assert.equal(formatInventoryQuantity('1.000125', 'kg'), '1,000125 kg');
});
