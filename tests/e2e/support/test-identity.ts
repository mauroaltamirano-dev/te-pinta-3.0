import type { TestInfo } from '@playwright/test';

export function testIdentity(testInfo: TestInfo, scenario: string): string {
    const project = testInfo.project.name.replace(/[^a-z0-9]+/gi, '-');
    const name = scenario.replace(/[^a-z0-9]+/gi, '-');

    return `${name}-${project}-w${testInfo.workerIndex}-r${testInfo.retry}`;
}
