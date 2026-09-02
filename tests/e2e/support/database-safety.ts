const safePostgresIdentifier = /^[a-z0-9_]+$/;
const testingDatabaseSegment = /(?:^|_)(?:test|testing)(?:_|$)/;

export function assertSafeTestingDatabaseName(database: string): void {
    if (!safePostgresIdentifier.test(database)) {
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
