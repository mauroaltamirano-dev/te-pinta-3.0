import { test as base } from '@playwright/test';
import { resetPlaywrightDatabase } from './test-database';

export { expect } from '@playwright/test';

// One shared application server/database cannot safely serve concurrent resets.
export const test = base.extend<{ _seededDatabase: void }>({
    _seededDatabase: [
        async ({ baseURL }, use, testInfo) => {
            if (!baseURL) {
                throw new Error(
                    'Database-backed browser tests require the configured application server.',
                );
            }

            resetPlaywrightDatabase(
                process.env.PLAYWRIGHT_DB_DATABASE ?? 'testing_playwright',
                testInfo.config.workers,
            );
            await use();
        },
        { auto: true },
    ],
});
