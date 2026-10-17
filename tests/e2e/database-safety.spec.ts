// These pure safety checks must not request the database-reset fixture.
import { expect, test } from '@playwright/test';
import {
    assertSafeSerialDatabaseReset,
    assertSafeTestingDatabaseName,
} from './support/database-safety';

test('database resets accept only explicit PostgreSQL test identifiers', () => {
    for (const name of [
        'testing_playwright',
        'testing_light_theme_e2e',
        'app_test_browser',
    ]) {
        expect(() => assertSafeSerialDatabaseReset(name, 1)).not.toThrow();
    }

    for (const name of [
        'tepinta',
        'production',
        'contest',
        'testing; DROP DATABASE tepinta',
        'testing-with-hyphens',
        'testing_' + 'a'.repeat(56),
    ]) {
        expect(() => assertSafeTestingDatabaseName(name)).toThrow();
    }
});

test('shared-database reset refuses parallel or invalid worker counts', () => {
    for (const workers of [0, 2, 4, Number.NaN, 1.5]) {
        expect(() =>
            assertSafeSerialDatabaseReset('testing_playwright', workers),
        ).toThrow(/one worker/);
    }
});
