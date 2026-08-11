import { defineConfig, devices } from '@playwright/test';

const playwrightDatabase =
    process.env.PLAYWRIGHT_DB_DATABASE ?? 'testing_playwright';

if (!/^[a-z0-9_]+$/.test(playwrightDatabase)) {
    throw new Error(
        'PLAYWRIGHT_DB_DATABASE must be a safe PostgreSQL identifier.',
    );
}

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8081',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'mobile-chromium', use: { ...devices['Pixel 7'] } },
    ],
    webServer: {
        command: `export APP_ENV=testing APP_DEBUG=false DB_DATABASE=${playwrightDatabase} DB_URL='' CACHE_STORE=array SESSION_DRIVER=database; php artisan serve --no-reload --host=127.0.0.1 --port=8081`,
        url: 'http://127.0.0.1:8081/up',
        reuseExistingServer: false,
        env: {
            APP_ENV: 'testing',
            APP_DEBUG: 'false',
            DB_DATABASE: playwrightDatabase,
            DB_URL: '',
            CACHE_STORE: 'array',
            SESSION_DRIVER: 'database',
        },
    },
});
