import { execFileSync } from 'node:child_process';
import { assertSafeTestingDatabaseName } from './support/database-safety';

export default function globalSetup(): void {
    const database = process.env.PLAYWRIGHT_DB_DATABASE ?? 'testing_playwright';

    assertSafeTestingDatabaseName(database);

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

    const env = {
        ...process.env,
        APP_ENV: 'testing',
        APP_DEBUG: 'false',
        DB_DATABASE: database,
        DB_URL: '',
        CACHE_STORE: 'array',
        SESSION_DRIVER: 'database',
    };

    execFileSync('php', ['artisan', 'migrate:fresh', '--force'], {
        env,
        stdio: 'inherit',
    });
    execFileSync(
        'php',
        [
            'artisan',
            'db:seed',
            '--class=Tests\\Support\\PlaywrightDatabaseSeeder',
            '--force',
        ],
        { env, stdio: 'inherit' },
    );
}
