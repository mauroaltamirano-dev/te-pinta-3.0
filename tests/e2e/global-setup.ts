import { execFileSync } from 'node:child_process';

export default function globalSetup(): void {
    const database = process.env.PLAYWRIGHT_DB_DATABASE ?? 'testing_playwright';

    if (!/^[a-z0-9_]+$/.test(database)) {
        throw new Error(
            'PLAYWRIGHT_DB_DATABASE must be a safe PostgreSQL identifier.',
        );
    }

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
