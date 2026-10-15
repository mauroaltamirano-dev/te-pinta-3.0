import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

// Vite renders the real JSX, not Playwright's component-testing JSX transport.
// No production demo route or new testing dependency is needed.
export async function adminPrimitivesMarkup() {
    const cacheDir = await mkdtemp(path.join(tmpdir(), 'aux1-components-'));
    const server = await createServer({
        configFile: false,
        cacheDir,
        resolve: { alias: { '@': path.resolve('resources/js') } },
        server: { middlewareMode: true, hmr: false, watch: null },
    });

    try {
        const { default: Fixture } = await server.ssrLoadModule(
            '/tests/e2e/support/admin-primitives-fixture.tsx',
        );

        return renderToStaticMarkup(createElement(Fixture));
    } finally {
        await server.close();
        await rm(cacheDir, { recursive: true, force: true });
    }
}
