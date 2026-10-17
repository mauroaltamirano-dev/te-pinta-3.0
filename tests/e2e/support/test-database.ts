import { execFileSync } from 'node:child_process';
import { assertSafeSerialDatabaseReset } from './database-safety';

export function resetPlaywrightDatabase(
    database: string,
    workers: number,
): void {
    assertSafeSerialDatabaseReset(database, workers);

    const env = {
        ...process.env,
        APP_ENV: 'testing',
        APP_DEBUG: 'false',
        DB_CONNECTION: 'pgsql',
        DB_DATABASE: database,
        DB_URL: '',
        CACHE_STORE: 'array',
        SESSION_DRIVER: 'database',
    };

    for (const args of [
        ['artisan', 'migrate:fresh', '--force'],
        [
            'artisan',
            'db:seed',
            '--class=Tests\\Support\\PlaywrightDatabaseSeeder',
            '--force',
        ],
    ]) {
        execFileSync('php', args, { env, stdio: 'pipe', timeout: 60_000 });
    }
}
