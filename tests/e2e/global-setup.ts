import { execFileSync } from 'node:child_process';
import type { FullConfig } from '@playwright/test';
import { assertSafeSerialDatabaseReset } from './support/database-safety';
import { resetPlaywrightDatabase } from './support/test-database';

export default function globalSetup(config: FullConfig): void {
    const database = process.env.PLAYWRIGHT_DB_DATABASE ?? 'testing_playwright';

    assertSafeSerialDatabaseReset(database, config.workers);

    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `if (! Illuminate\\Support\\Facades\\DB::table("pg_database")->where("datname", "${database}")->exists()) { Illuminate\\Support\\Facades\\DB::statement("CREATE DATABASE ${database}"); }`,
        ],
        { stdio: 'inherit' },
    );

    resetPlaywrightDatabase(database, config.workers);
}
