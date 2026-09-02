import { defineConfig, devices } from '@playwright/test';
import { assertSafeTestingDatabaseName } from './tests/e2e/support/database-safety';

const playwrightDatabase =
    process.env.PLAYWRIGHT_DB_DATABASE ?? 'testing_playwright';
const playwrightPort = process.env.PLAYWRIGHT_PORT ?? '8081';
const playwrightBaseUrl =
    process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${playwrightPort}`;

assertSafeTestingDatabaseName(playwrightDatabase);

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './storage/app/playwright-results',
    globalSetup: './tests/e2e/global-setup.ts',
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: playwrightBaseUrl,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'mobile-chromium', use: { ...devices['Pixel 7'] } },
    ],
    webServer: {
        command: `export APP_ENV=testing APP_DEBUG=false DB_DATABASE=${playwrightDatabase} DB_URL='' CACHE_STORE=array SESSION_DRIVER=database; php artisan serve --no-reload --host=127.0.0.1 --port=${playwrightPort}`,
        url: `${playwrightBaseUrl}/up`,
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
