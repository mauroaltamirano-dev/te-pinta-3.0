import assert from 'node:assert/strict';
import test from 'node:test';
import { formatMoney } from '../../resources/js/lib/format.ts';

const legacy = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    maximumFractionDigits: 0,
});

test('money keeps the es-AR peso format of the formatters it replaces', () => {
    for (const amount of [0, 1800, 16650, 1234567, -2500, 99.5]) {
        assert.equal(formatMoney(amount), legacy.format(amount));
    }

    assert.equal(formatMoney(16650).replace(/\s/g, ''), '$16.650');
});
