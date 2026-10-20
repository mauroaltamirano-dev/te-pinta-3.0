import assert from 'node:assert/strict';
import test from 'node:test';
import {
    managementNavigation,
    managementOperationsHref,
    requestedManagementSection,
} from '../../resources/js/features/management/navigation.ts';

const tabs = (url) =>
    managementNavigation(url).items.map(({ label, href }) => [label, href]);

test('Gestión sections are Panorama, Movimientos, Billeteras, Compras and Auditoría', () => {
    assert.deepEqual(tabs('/admin/management/audit'), [
        ['Panorama', '/admin/management?section=panorama'],
        ['Movimientos', '/admin/management?section=movements'],
        ['Billeteras', '/admin/management?section=wallets'],
        ['Compras', '/admin/management/purchases'],
        ['Auditoría', '/admin/management/audit'],
    ]);
});

test('ledger sections keep the reporting filters of the current page', () => {
    assert.deepEqual(tabs('/admin/management?history_period=month&page=2'), [
        [
            'Panorama',
            '/admin/management?history_period=month&page=2&section=panorama',
        ],
        [
            'Movimientos',
            '/admin/management?history_period=month&page=2&section=movements',
        ],
        [
            'Billeteras',
            '/admin/management?history_period=month&page=2&section=wallets',
        ],
        ['Compras', '/admin/management/purchases'],
        ['Auditoría', '/admin/management/audit'],
    ]);
    assert.equal(
        managementOperationsHref('/admin/management?history_period=month'),
        '/admin/management?history_period=month&section=operations',
    );
});

test('the current section follows the page, and Registrar operación is not a tab', () => {
    const current = (url) => managementNavigation(url).currentHref;

    assert.equal(
        current('/admin/management'),
        '/admin/management?section=panorama',
    );
    assert.equal(
        current('/admin/management?type=income'),
        '/admin/management?type=income&section=movements',
    );
    assert.equal(
        current('/admin/management?section=wallets'),
        '/admin/management?section=wallets',
    );
    assert.equal(current('/admin/management?section=operations'), null);
    assert.equal(
        current('/admin/management/purchases'),
        '/admin/management/purchases',
    );
    assert.equal(
        current('/admin/management/purchases/create'),
        '/admin/management/purchases',
    );
    assert.equal(
        current('/admin/management/purchases/12?page=2'),
        '/admin/management/purchases',
    );
    assert.equal(current('/admin/management/audit'), '/admin/management/audit');
    assert.ok(
        !tabs('/admin/management').some(
            ([label]) => label === 'Registrar operación',
        ),
    );
});

test('the requested ledger section keeps its previous rules', () => {
    assert.equal(requestedManagementSection('/admin/management'), 'panorama');
    assert.equal(
        requestedManagementSection('/admin/management?section=operations'),
        'operations',
    );
    assert.equal(
        requestedManagementSection('/admin/management?section=unknown'),
        'panorama',
    );
    assert.equal(
        requestedManagementSection('/admin/management?wallet_id=3'),
        'movements',
    );
    assert.equal(
        requestedManagementSection('/admin/management?section=wallets&page=2'),
        'wallets',
    );
});
