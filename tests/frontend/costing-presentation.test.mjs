import assert from 'node:assert/strict';
import test from 'node:test';
import {
    basisPointsToPercent,
    percentToBasisPoints,
    presentationName,
} from '../../resources/js/features/inventory/costing/types.ts';

test('overhead is typed as a percentage and stored as exact basis points', () => {
    assert.equal(percentToBasisPoints('10'), 1000);
    assert.equal(percentToBasisPoints('12,5'), 1250);
    assert.equal(percentToBasisPoints('12.05'), 1205);
    assert.equal(percentToBasisPoints(' 0 '), 0);
    assert.equal(percentToBasisPoints('100'), 10000);
    assert.equal(percentToBasisPoints('100,01'), null);
    assert.equal(percentToBasisPoints('1,234'), null);
    assert.equal(percentToBasisPoints('-5'), null);
    assert.equal(percentToBasisPoints(''), null);
});

test('stored basis points prefill as a percentage', () => {
    assert.equal(basisPointsToPercent(1000), '10');
    assert.equal(basisPointsToPercent(1250), '12,5');
    assert.equal(basisPointsToPercent(5), '0,05');
});

test('sale presentations have plain names', () => {
    assert.equal(presentationName('unit', 1), 'Unidad');
    assert.equal(presentationName('half_dozen', 6), 'Media docena');
    assert.equal(presentationName('dozen', 12), 'Docena');
    assert.equal(presentationName('unit', 3), '3 unidades');
});
