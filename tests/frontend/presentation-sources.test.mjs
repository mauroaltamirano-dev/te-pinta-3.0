import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import test from 'node:test';

const root = new URL('../../resources/js/', import.meta.url).pathname;

function sources(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);

        if (entry.isDirectory()) {
            // Generated Wayfinder routes and actions are not presentation code.
            return ['routes', 'actions', 'wayfinder'].includes(entry.name) &&
                directory === root.replace(/\/$/, '')
                ? []
                : sources(path);
        }

        return /\.tsx?$/.test(entry.name) ? [path] : [];
    });
}

const files = sources(root.replace(/\/$/, '')).map((path) => ({
    path: relative(root, path),
    code: readFileSync(path, 'utf8'),
}));

test('order status labels have a single source', () => {
    const definitions = files
        .filter(({ code }) =>
            /:\s*Record<OrderStatus,\s*string>\s*=/.test(code),
        )
        .map(({ path, code }) => [
            path,
            [...code.matchAll(/(\w+):\s*Record<OrderStatus,\s*string>\s*=/g)]
                .map((match) => match[1])
                .filter((name) => /Labels$/.test(name)),
        ])
        .filter(([, names]) => names.length > 0);

    assert.deepEqual(definitions, [
        ['features/orders/presentation.ts', ['orderStatusLabels']],
    ]);
    assert.deepEqual(
        files
            .filter(({ code }) => code.includes('dashboardStatusLabels'))
            .map(({ path }) => path),
        [],
    );
});

test('money is formatted by lib/format.ts only', () => {
    // Public pages adopt it when the public redesign rebases (S-01).
    const pending = [
        'pages/public/menu.tsx',
        'pages/public/product-detail.tsx',
    ];

    assert.deepEqual(
        files
            .filter(({ code }) => /currency:\s*'ARS'/.test(code))
            .map(({ path }) => path)
            .filter((path) => !pending.includes(path)),
        ['lib/format.ts'],
    );
});
