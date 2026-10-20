import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    activeAdminEntry,
    activeAdminSection,
    adminBreadcrumbRoot,
    adminMobilePrimaryEntries,
    adminNewOrder,
    visibleAdminNavigation,
} from '../../resources/js/features/admin-navigation/model.ts';

// RolePermissionsFixtureTest keeps this file equal to Role::permissions().
const roles = JSON.parse(
    readFileSync(new URL('./fixtures/role-permissions.json', import.meta.url)),
);

const outline = (groups) =>
    groups.map(({ label, entries }) => [
        label,
        entries.map((entry) => entry.label),
    ]);

test('owners see 9 entries in 6 groups, one icon per destination', () => {
    const groups = visibleAdminNavigation(roles.owner);

    assert.deepEqual(outline(groups), [
        ['Operación', ['Vender', 'Pedidos', 'Clientes']],
        ['Resumen', ['Panel']],
        ['Catálogo', ['Productos', 'Marketing']],
        ['Stock y costos', ['Inventario']],
        ['Finanzas', ['Gestión']],
        ['Configuración', ['Configuración']],
    ]);

    const entries = groups.flatMap((group) => group.entries);
    assert.equal(new Set(entries.map((entry) => entry.icon)).size, 9);
    assert.deepEqual(
        entries.map((entry) => entry.href),
        [
            '/admin',
            '/admin/orders',
            '/admin/customers',
            '/admin/dashboard',
            '/admin/settings/products',
            '/admin/marketing',
            '/admin/inventory',
            '/admin/management',
            '/admin/settings/general',
        ],
    );
});

test('each role sees only the entries its permissions open', () => {
    assert.deepEqual(outline(visibleAdminNavigation(roles.manager)), [
        ['Operación', ['Vender', 'Pedidos', 'Clientes']],
        ['Resumen', ['Panel']],
        ['Catálogo', ['Productos', 'Marketing']],
        ['Stock y costos', ['Inventario']],
        ['Finanzas', ['Gestión']],
        ['Configuración', ['Configuración']],
    ]);
    assert.deepEqual(outline(visibleAdminNavigation(roles.cashier)), [
        ['Operación', ['Vender', 'Pedidos', 'Clientes']],
        ['Resumen', ['Panel']],
        ['Catálogo', ['Productos']],
    ]);
    assert.deepEqual(outline(visibleAdminNavigation(roles.waiter)), [
        ['Operación', ['Vender', 'Pedidos', 'Clientes']],
        ['Catálogo', ['Productos']],
    ]);
    // Without orders.manage, /admin redirects to the order list: no "Vender".
    assert.deepEqual(outline(visibleAdminNavigation(roles.kitchen)), [
        ['Operación', ['Pedidos']],
        ['Catálogo', ['Productos']],
        ['Stock y costos', ['Inventario']],
    ]);
    assert.deepEqual(outline(visibleAdminNavigation(roles.accountant)), [
        ['Operación', ['Pedidos', 'Clientes']],
        ['Resumen', ['Panel']],
        ['Catálogo', ['Productos']],
        ['Stock y costos', ['Inventario']],
        ['Finanzas', ['Gestión']],
    ]);
    assert.deepEqual(visibleAdminNavigation([]), []);
});

test('Configuración opens the first section the user may use', () => {
    const settings = (permissions) =>
        visibleAdminNavigation(permissions)
            .flatMap((group) => group.entries)
            .find((entry) => entry.label === 'Configuración');

    assert.deepEqual(
        settings(roles.owner).sections.map(({ label, href }) => [label, href]),
        [
            ['General', '/admin/settings/general'],
            ['Plano del salón', '/admin/restaurant/floor'],
            ['Usuarios', '/admin/users'],
        ],
    );
    assert.deepEqual(
        settings(roles.manager).sections.map(({ label }) => label),
        ['General', 'Plano del salón'],
    );
    assert.equal(settings(['floor.manage']).href, '/admin/restaurant/floor');
    assert.equal(settings(['users.manage']).href, '/admin/users');
    assert.equal(settings(['catalog.manage']).href, '/admin/settings/general');
    // The floor route stays open to orders.manage, but only floor.manage edits it.
    assert.equal(settings(['orders.manage']), undefined);
});

test('the active entry follows every page it absorbs', () => {
    const groups = visibleAdminNavigation(roles.owner);
    const active = (url) => activeAdminEntry(groups, url)?.label ?? null;

    assert.equal(active('/admin'), 'Vender');
    assert.equal(active('/admin?customer=3'), 'Vender');
    assert.equal(active('/admin/print-station'), 'Vender');
    assert.equal(active('/admin/orders'), 'Pedidos');
    assert.equal(active('/admin/orders/create'), 'Pedidos');
    assert.equal(active('/admin/orders/trash?page=2'), 'Pedidos');
    assert.equal(active('/admin/customers/7'), 'Clientes');
    assert.equal(active('/admin/dashboard?period=week'), 'Panel');
    assert.equal(active('/admin/settings/products?category=2'), 'Productos');
    assert.equal(active('/admin/marketing'), 'Marketing');
    assert.equal(active('/admin/marketing/instagram'), 'Marketing');
    assert.equal(active('/admin/inventory'), 'Inventario');
    assert.equal(active('/admin/inventory/costing-policies'), 'Inventario');
    assert.equal(active('/admin/inventory/recipes/4'), 'Inventario');
    assert.equal(active('/admin/management?section=wallets'), 'Gestión');
    assert.equal(active('/admin/management/purchases/create'), 'Gestión');
    assert.equal(active('/admin/settings/general'), 'Configuración');
    assert.equal(active('/admin/restaurant/floor'), 'Configuración');
    assert.equal(active('/admin/users'), 'Configuración');
    assert.equal(active('/admin/analytics'), null);
    assert.equal(active('/admin/ordersx'), null);
});

test('the mobile bar keeps Vender, Pedidos and Panel when allowed', () => {
    const labels = (permissions) =>
        adminMobilePrimaryEntries(visibleAdminNavigation(permissions)).map(
            (entry) => entry.label,
        );

    assert.deepEqual(labels(roles.owner), ['Vender', 'Pedidos', 'Panel']);
    assert.deepEqual(labels(roles.waiter), ['Vender', 'Pedidos']);
    assert.deepEqual(labels(roles.kitchen), ['Pedidos']);
});

const entry = (permissions, label) =>
    visibleAdminNavigation(permissions)
        .flatMap((group) => group.entries)
        .find((item) => item.label === label);

test('absorbed pages are sections of their module', () => {
    const sections = (label) =>
        entry(roles.owner, label).sections.map((section) => [
            section.label,
            section.href,
        ]);

    assert.deepEqual(sections('Inventario'), [
        ['Insumos', '/admin/inventory'],
        ['Recetas', '/admin/inventory/recipes'],
        ['Producción', '/admin/inventory/production'],
        ['Ajustes de stock', '/admin/inventory/adjustments'],
        ['Costos', '/admin/inventory/costing'],
    ]);
    assert.deepEqual(sections('Marketing'), [
        ['Producto destacado', '/admin/marketing'],
        ['Instagram', '/admin/marketing/instagram'],
    ]);
    assert.deepEqual(entry(roles.kitchen, 'Inventario').sections.length, 5);
    assert.deepEqual(sections('Vender'), [
        ['Mostrador', '/admin'],
        ['Impresión', '/admin/print-station'],
    ]);
    assert.deepEqual(
        entry(roles.waiter, 'Vender').sections.map(({ label }) => label),
        ['Mostrador', 'Impresión'],
    );
});

test('the active section follows nested pages', () => {
    const active = (label, url) =>
        activeAdminSection(entry(roles.owner, label), url)?.label ?? null;

    assert.equal(active('Inventario', '/admin/inventory'), 'Insumos');
    assert.equal(active('Vender', '/admin'), 'Mostrador');
    assert.equal(active('Vender', '/admin?customer=3'), 'Mostrador');
    assert.equal(active('Vender', '/admin/print-station'), 'Impresión');
    assert.equal(
        active('Inventario', '/admin/inventory?category=2'),
        'Insumos',
    );
    assert.equal(active('Inventario', '/admin/inventory/recipes/4'), 'Recetas');
    assert.equal(
        active('Inventario', '/admin/inventory/production/create'),
        'Producción',
    );
    assert.equal(
        active('Inventario', '/admin/inventory/adjustments/9'),
        'Ajustes de stock',
    );
    assert.equal(active('Inventario', '/admin/inventory/costing'), 'Costos');
    assert.equal(
        active('Inventario', '/admin/inventory/costing-policies'),
        'Costos',
    );
    assert.equal(
        active('Inventario', '/admin/inventory/product-consumption/3'),
        null,
    );
    assert.equal(active('Marketing', '/admin/marketing'), 'Producto destacado');
    assert.equal(
        active('Marketing', '/admin/marketing/products/5/featured'),
        'Producto destacado',
    );
    assert.equal(
        active('Marketing', '/admin/marketing/instagram'),
        'Instagram',
    );
    assert.equal(
        active('Configuración', '/admin/restaurant/floor'),
        'Plano del salón',
    );
    assert.equal(active('Configuración', '/admin/users'), 'Usuarios');
});

test('the breadcrumb root is Panel only for users who can open it', () => {
    assert.deepEqual(adminBreadcrumbRoot(roles.accountant), [
        { title: 'Panel', href: '/admin/dashboard' },
    ]);
    assert.deepEqual(adminBreadcrumbRoot(roles.kitchen), []);
});

test('"Nuevo pedido" always opens the POS, for roles that can sell', () => {
    assert.deepEqual(adminNewOrder(roles.waiter), {
        href: '/admin',
        advancedHref: '/admin/orders/create',
    });
    assert.deepEqual(adminNewOrder(roles.cashier), adminNewOrder(roles.owner));
    assert.equal(adminNewOrder(roles.kitchen), null);
    assert.equal(adminNewOrder(roles.accountant), null);
});
