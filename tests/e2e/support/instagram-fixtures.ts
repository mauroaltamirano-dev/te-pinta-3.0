import { execFileSync } from 'node:child_process';
import type { TestInfo } from '@playwright/test';
import { assertSafeTestingDatabaseName } from './database-safety';
import { testIdentity } from './test-identity';

export type InstagramFixture = {
    identity: string;
    assets: {
        in_use: { id: number; name: string };
        replacement: { id: number; name: string };
    };
    schedules: {
        first: { id: number; name: string };
        second: { id: number; name: string };
    };
    publications: Record<string, { id: number; name: string }>;
};

export function createInstagramFixture(
    testInfo: TestInfo,
    scenario: string,
): InstagramFixture {
    const database = process.env.PLAYWRIGHT_DB_DATABASE ?? 'testing_playwright';
    assertSafeTestingDatabaseName(database);
    const identity = testIdentity(testInfo, scenario);
    const phpIdentity = JSON.stringify(identity);
    const marker = '__PLAYWRIGHT_INSTAGRAM_FIXTURE__';
    const output = execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `echo '${marker}'.json_encode(Tests\\Support\\InstagramPlaywrightFixture::create(${phpIdentity}));`,
        ],
        {
            encoding: 'utf8',
            env: {
                ...process.env,
                APP_ENV: 'testing',
                APP_DEBUG: 'false',
                DB_DATABASE: database,
                DB_URL: '',
                CACHE_STORE: 'array',
                SESSION_DRIVER: 'database',
            },
        },
    );
    const payload = output.slice(output.lastIndexOf(marker) + marker.length);

    return JSON.parse(payload) as InstagramFixture;
}
