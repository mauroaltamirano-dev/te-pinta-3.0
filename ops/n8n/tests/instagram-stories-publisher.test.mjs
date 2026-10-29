import assert from 'node:assert/strict';
import { Buffer } from 'node:buffer';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const workflow = JSON.parse(
    readFileSync(
        new URL(
            '../workflows/instagram-stories-publisher.json',
            import.meta.url,
        ),
    ),
);
const node = (name) => {
    const result = workflow.nodes.find((item) => item.name === name);
    assert.ok(result, `Missing node: ${name}`);

    return result;
};
const mediaUrl = (expires = Math.floor(Date.now() / 1000) + 5400) =>
    `https://media.example.test/media/instagram/7/story.jpg?expires=${expires}&signature=${'a'.repeat(64)}`;
const publication = () => ({
    body: { id: 123, attempt: 1, asset: { media_url: mediaUrl() } },
});
const evaluate = (expression, data) =>
    vm.runInNewContext(expression.slice(3, -2), {
        $json: data,
        $execution: { id: 'private-test' },
        $: () => ({ item: { json: publication() } }),
    });
const failure = (data, name = 'Report Container Failure') =>
    JSON.parse(evaluate(node(name).parameters.body, data));
const metaFailure = (
    statusCode = 400,
    code = 9004,
    error_subcode = 2207052,
) => ({
    statusCode,
    body: { error: { code, error_subcode } },
});
async function preflight(response, url = mediaUrl()) {
    const calls = [];
    const code = node('Validate Story JPEG').parameters.jsCode;
    const run = new Function(
        '$json',
        `return (async function () {${code}\n}).call(this);`,
    );
    const result = await run.call(
        {
            helpers: {
                httpRequest: async (options) => {
                    calls.push(options);

                    if (response instanceof Error) {
                        throw response;
                    }

                    return response;
                },
            },
        },
        { body: { id: 123, asset: { media_url: url } } },
    );

    return {
        result: result.json.resource_preflight,
        calls,
        output: result.json,
    };
}
const jpeg = {
    statusCode: 200,
    headers: { 'content-type': 'image/jpeg' },
    body: Buffer.from(
        readFileSync(
            new URL('./fixtures/jpeg-1x1.base64', import.meta.url),
            'utf8',
        ).trim(),
        'base64',
    ),
};

test('the incident rejection is retryable only before a container exists', () => {
    assert.equal(failure(metaFailure()).retryable, true);
    assert.equal(
        failure({
            ...metaFailure(),
            body: { ...metaFailure().body, id: 'existing-container' },
        }).retryable,
        false,
    );
});

test('other client errors remain terminal and existing server/network retries are preserved', () => {
    for (const response of [
        metaFailure(400, 100),
        metaFailure(400, 9004, 1),
        metaFailure(401),
        metaFailure(403),
    ]) {
        assert.equal(failure(response).retryable, false);
    }

    for (const status of [429, 500, undefined]) {
        assert.equal(failure({ statusCode: status }).retryable, true);
    }
});

test('valid JPEG preflight is bounded, unauthenticated and does not retain image bytes', async () => {
    const { result, calls, output } = await preflight(jpeg);
    assert.equal(result.ok, true);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].method, 'GET');
    assert.equal(calls[0].disableFollowRedirect, true);
    assert.equal(calls[0].timeout, 15000);
    assert.equal(calls[0].skipSslCertificateValidation, undefined);
    assert.equal(calls[0].headers?.Authorization, undefined);
    assert.equal(output.body.id, 123);
    assert.equal(output.binary, undefined);
    assert.equal(JSON.stringify(result).includes('signature='), false);
});

test('expired or malformed signed resources fail without a network request', async () => {
    for (const url of [
        mediaUrl(1),
        'http://media.example.test/story.jpg',
        'https://user:password@media.example.test/story.jpg',
    ]) {
        const { result, calls } = await preflight(jpeg, url);
        assert.equal(result.ok, false);
        assert.equal(result.retryable, false);
        assert.equal(calls.length, 0);
    }
});

test('inaccessible resources are sanitized and bounded by the existing POS retry policy', async () => {
    for (const response of [
        new Error(`timeout ${mediaUrl()}`),
        { statusCode: 503, body: 'unavailable' },
        { statusCode: 429 },
    ]) {
        const { result } = await preflight(response);
        assert.equal(result.ok, false);
        assert.equal(result.retryable, true);
        assert.equal(JSON.stringify(result).includes('signature='), false);
        const callback = failure({ resource_preflight: result });
        assert.equal(callback.retryable, true);
        assert.equal(callback.error.includes('signature='), false);
    }
});

test('HTML, redirects, forbidden and missing assets never reach Meta', async () => {
    const responses = [
        { ...jpeg, headers: { 'content-type': 'text/html' } },
        { ...jpeg, body: Buffer.from('<html>challenge</html>') },
        { ...jpeg, body: Buffer.from('ffd8ff', 'hex') },
        ...[302, 403, 404].map((statusCode) => ({ statusCode })),
    ];

    for (const response of responses) {
        const { result } = await preflight(response);
        assert.equal(result.ok, false);
        assert.equal(result.retryable, false);
    }

    assert.equal(
        workflow.connections['Publication Available?'].main[0][0].node,
        'Validate Story JPEG',
    );
    assert.equal(
        workflow.connections['Validate Story JPEG'].main[0][0].node,
        'Resource Ready?',
    );
    assert.equal(
        workflow.connections['Resource Ready?'].main[0][0].node,
        'Create Story Container',
    );
    assert.equal(
        workflow.connections['Resource Ready?'].main[1][0].node,
        'Report Container Failure',
    );
});

test('retries never bypass claiming, duplicate a publish request, or replay ambiguous results', () => {
    assert.equal(workflow.active, false);

    for (const name of ['Create Story Container', 'Publish Story Container']) {
        assert.notEqual(node(name).retryOnFail, true);
    }

    assert.equal(
        workflow.connections['Failure Callback Accepted?'].main[0].length,
        0,
    );
    const callback = failure(
        { statusCode: 500 },
        'Report Ambiguous Publish Failure',
    );
    assert.equal(callback.retryable, false);
    assert.equal(callback.ambiguous, true);
    assert.equal(
        workflow.connections['Story Published?'].main[1][0].node,
        'Report Ambiguous Publish Failure',
    );
    assert.equal(
        workflow.connections['Published Callback Accepted?'].main[1][0].node,
        'Stop - Ambiguous Published State',
    );
    assert.equal(
        workflow.connections['Claim Next Publication'].main[0][0].node,
        'Publication Available?',
    );
});
