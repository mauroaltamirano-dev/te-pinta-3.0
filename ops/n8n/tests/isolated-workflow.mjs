import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

// Real n8n engine and HTTP helpers; every service is a private HTTPS fixture.
const directory = mkdtempSync(join(tmpdir(), 'instagram-n8n-test-'));
const container = directory.split('/').at(-1);
try {
    execFileSync(
        'openssl',
        [
            'req',
            '-x509',
            '-newkey',
            'rsa:2048',
            '-nodes',
            '-subj',
            '/CN=media.example.test',
            '-addext',
            'subjectAltName=DNS:media.example.test,DNS:meta.example.test,DNS:pos.example.test',
            '-days',
            '1',
            '-keyout',
            join(directory, 'key.pem'),
            '-out',
            join(directory, 'cert.pem'),
        ],
        { stdio: 'ignore' },
    );
    const workflow = JSON.parse(
        readFileSync(
            new URL(
                '../workflows/instagram-stories-publisher.json',
                import.meta.url,
            ),
        ),
    );
    // Workflow export as it was before the incident 36779 fix.
    const baseline = JSON.parse(
        readFileSync(
            new URL(
                './fixtures/instagram-stories-publisher.baseline-36779.json',
                import.meta.url,
            ),
        ),
    );
    for (const [index, fixture] of [baseline, workflow].entries()) {
        fixture.id =
            index === 0 ? 'privateBaseline36779' : 'privateInstagram36779';
        for (const node of fixture.nodes) {
            delete node.credentials;
            if (node.type === 'n8n-nodes-base.httpRequest') {
                node.parameters.authentication = 'none';
                delete node.parameters.genericAuthType;
            }
            if (
                node.type === 'n8n-nodes-base.scheduleTrigger' ||
                node.type === 'n8n-nodes-base.wait'
            ) {
                node.type = node.type.endsWith('wait')
                    ? 'n8n-nodes-base.noOp'
                    : 'n8n-nodes-base.manualTrigger';
                node.typeVersion = 1;
                node.parameters = {};
            }
        }
    }
    writeFileSync(
        join(directory, 'workflow.json'),
        JSON.stringify([baseline, workflow]),
    );
    writeFileSync(
        join(directory, 'jpeg.base64'),
        readFileSync(new URL('./fixtures/jpeg-1x1.base64', import.meta.url)),
    );
    writeFileSync(
        join(directory, 'run.cjs'),
        String.raw`
const assert = require('node:assert/strict');
const fs = require('node:fs');
const https = require('node:https');
const { spawn } = require('node:child_process');
const { DatabaseSync } = require('node:sqlite');
function lastStatus() {
    const db = new DatabaseSync('/tmp/private-n8n/.n8n/database.sqlite', { readOnly: true });
    const row = db.prepare('SELECT id,status FROM execution_entity ORDER BY id DESC LIMIT 1').get();
    db.close();
    return row;
}
let state;
const server = https.createServer({ key: fs.readFileSync('/fixtures/key.pem'), cert: fs.readFileSync('/fixtures/cert.pem') }, (req, res) => {
    let body = '';
    req.on('data', (chunk) => { body += chunk; });
    req.on('end', () => {
        const reply = (status, value, mime = 'application/json') => {
            res.writeHead(status, { 'content-type': mime });
            res.end(Buffer.isBuffer(value) ? value : JSON.stringify(value));
        };
        if (req.url.startsWith('/media/instagram/')) {
            state.gets++;
            if (state.name === 'unreachable') return req.socket.destroy();
            return state.name === 'html' ? reply(200, '<html>challenge</html>', 'text/html')
                : reply(200, Buffer.from(fs.readFileSync('/fixtures/jpeg.base64', 'utf8').trim(), 'base64'), 'image/jpeg');
        }
        if (req.url.endsWith('/pending')) {
            state.claims++;
            if (state.name === 'no-work') return reply(204, '');
            const expires = state.name === 'expired' ? 1 : Math.floor(Date.now() / 1000) + 5400;
            return reply(200, { id: 123, attempt: 1, asset: { media_url:
                'https://media.example.test:8443/media/instagram/7/story.jpg?expires=' + expires + '&signature=' + 'a'.repeat(64) } });
        }
        if (req.url === '/v26.0/private-account/media') {
            state.creates++;
            const params = new URLSearchParams(body);
            assert.equal(params.get('media_type'), 'STORIES');
            assert.ok(params.get('image_url').startsWith('https://media.example.test:8443/'));
            if (state.name === 'fetch-rejected') return reply(400, { error: { code: 9004, error_subcode: 2207052 } });
            if (state.name === 'other-400') return reply(400, { error: { code: 100 } });
            return reply(200, { id: 'private-container' });
        }
        if (req.url.startsWith('/v26.0/private-container?')) return reply(200, { status_code: 'FINISHED' });
        if (req.url === '/v26.0/private-account/media_publish') {
            state.publishes++;
            assert.equal(new URLSearchParams(body).get('creation_id'), 'private-container');
            if (state.name === 'ambiguous') return req.socket.destroy();
            return reply(200, { id: 'private-media' });
        }
        if (req.url.endsWith('/123/failed')) {
            state.failed = JSON.parse(body);
            assert.ok(!body.includes('signature='));
            return reply(200, { status: state.failed.retryable ? 'pending' : 'failed' });
        }
        if (req.url.endsWith('/123/published')) {
            state.published = JSON.parse(body);
            return reply(state.name === 'callback-failed' ? 500 : 200, { status: 'published' });
        }
        reply(404, {});
    });
});
async function execute(args = ['execute', '--id=privateInstagram36779', '--rawOutput']) {
    return await new Promise((resolve, reject) => {
        const child = spawn('n8n', args, { env: process.env });
        let output = '';
        child.stdout.on('data', (chunk) => { output += chunk; });
        child.stderr.on('data', (chunk) => { output += chunk; });
        child.on('error', reject);
        child.on('exit', (code) => resolve({ code, output }));
    });
}
server.listen(8443, '127.0.0.1', async () => {
    try {
        const imported = await execute(['import:workflow', '--input=/fixtures/workflow.json']);
        assert.equal(imported.code, 0, imported.output);
        for (const name of ['baseline', 'valid', 'fetch-rejected', 'other-400', 'html', 'expired', 'unreachable', 'ambiguous', 'callback-failed', 'no-work']) {
            state = { name, gets: 0, claims: 0, creates: 0, publishes: 0 };
            const result = await execute(['execute', '--id=' + (name === 'baseline' ? 'privateBaseline36779' : 'privateInstagram36779'), '--rawOutput']);
            const execution = lastStatus();
            assert.equal(execution.status, name === 'callback-failed' ? 'error' : 'success', name + ': ' + result.output.slice(-3000));
            if (result.code !== 0 && name !== 'callback-failed') assert.match(result.output, /Converting circular structure to JSON/, name);
            if (name === 'baseline') assert.match(result.output, /Converting circular structure to JSON/, 'baseline CLI serialization defect');
            if (name === 'valid' && state.publishes !== 1) {
                console.error(JSON.stringify(state));
                console.error(result.output.slice(-10000));
            }
            assert.equal(state.claims, 1, name);
            assert.equal(state.publishes, ['baseline', 'valid', 'ambiguous', 'callback-failed'].includes(name) ? 1 : 0, name);
            assert.equal(state.creates, ['html', 'expired', 'unreachable', 'no-work'].includes(name) ? 0 : 1, name);
            if (['fetch-rejected', 'unreachable'].includes(name)) assert.equal(state.failed?.retryable, true, name);
            if (['html', 'expired', 'other-400', 'ambiguous'].includes(name)) assert.equal(state.failed?.retryable, false, name);
            if (name === 'ambiguous') assert.equal(state.failed.ambiguous, true);
            if (name === 'callback-failed') assert.match(result.output, /Error executing workflow/, 'published callback failure must stop');
            console.log(JSON.stringify({ case: name, engine_status: execution.status, claims: state.claims, jpeg_gets: state.gets, creates: state.creates, publishes: state.publishes, pass: true }));
        }
        server.close();
    } catch (error) {
        console.error(error);
        process.exitCode = 1;
        server.close();
    }
});
`,
    );
    execFileSync('chmod', ['644', join(directory, 'key.pem')]);
    const args = [
        'run',
        '--rm',
        '--pull=never',
        '--name',
        container,
        '--network',
        'none',
        '--read-only',
        '--cap-drop',
        'ALL',
        '--security-opt',
        'no-new-privileges:true',
        '--tmpfs',
        '/tmp:rw,exec,mode=1777',
        '-v',
        `${directory}:/fixtures:ro`,
        ...['media', 'meta', 'pos'].flatMap((name) => [
            '--add-host',
            `${name}.example.test:127.0.0.1`,
        ]),
    ];
    const environment = {
        N8N_USER_FOLDER: '/tmp/private-n8n',
        N8N_DIAGNOSTICS_ENABLED: 'false',
        N8N_VERSION_NOTIFICATIONS_ENABLED: 'false',
        N8N_RUNNERS_ENABLED: 'true',
        N8N_BLOCK_ENV_ACCESS_IN_NODE: 'false',
        N8N_LOG_LEVEL: 'error',
        NODE_EXTRA_CA_CERTS: '/fixtures/cert.pem',
        POS_BASE_URL: 'https://pos.example.test:8443',
        META_GRAPH_BASE_URL: 'https://meta.example.test:8443',
        META_GRAPH_API_VERSION: 'v26.0',
        META_INSTAGRAM_USER_ID: 'private-account',
    };
    for (const [key, value] of Object.entries(environment))
        args.push('-e', `${key}=${value}`);
    args.push(
        '--entrypoint',
        'node',
        'docker.n8n.io/n8nio/n8n:2.32.7',
        '/fixtures/run.cjs',
    );
    execFileSync('docker', args, { stdio: 'inherit', timeout: 240000 });
} finally {
    try {
        execFileSync('docker', ['rm', '-f', container], { stdio: 'ignore' });
    } catch {
        // --rm already removed a completed fixture container.
    }
    rmSync(directory, { recursive: true, force: true });
}
