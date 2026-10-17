const safePostgresIdentifier = /^[a-z0-9_]+$/;
const testingDatabaseSegment = /(?:^|_)(?:test|testing)(?:_|$)/;

export function assertSafeTestingDatabaseName(database: string): void {
    if (!safePostgresIdentifier.test(database) || database.length > 63) {
        throw new Error(
            'PLAYWRIGHT_DB_DATABASE must be a safe PostgreSQL identifier.',
        );
    }

    if (!testingDatabaseSegment.test(database)) {
        throw new Error(
            'PLAYWRIGHT_DB_DATABASE must contain a standalone test or testing segment.',
        );
    }
}

export function assertSafeSerialDatabaseReset(
    database: string,
    workers: number,
): void {
    assertSafeTestingDatabaseName(database);

    if (workers !== 1) {
        throw new Error(
            'The shared Playwright database requires exactly one worker; parallel database resets are unsafe.',
        );
    }
}
