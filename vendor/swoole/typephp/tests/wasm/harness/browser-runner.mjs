import fs from 'node:fs/promises';
import http from 'node:http';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer-core';

const [, , artifactDir, chromePath, optionsFile] = process.argv;
if (!artifactDir || !chromePath || !optionsFile) {
    throw new Error('Usage: browser-runner.mjs <artifact-dir> <chrome> <options.json>');
}

const harnessDir = path.dirname(fileURLToPath(import.meta.url));
const shimDir = path.join(harnessDir, 'node_modules', '@bytecodealliance', 'preview2-shim');
const options = JSON.parse(await fs.readFile(optionsFile, 'utf8'));
const stdin = await new Promise((resolve, reject) => {
    let value = '';
    process.stdin.setEncoding('utf8');
    process.stdin.on('data', (chunk) => value += chunk);
    process.stdin.on('end', () => resolve(value));
    process.stdin.on('error', reject);
});

const contentTypes = new Map([
    ['.html', 'text/html; charset=utf-8'],
    ['.js', 'text/javascript; charset=utf-8'],
    ['.json', 'application/json; charset=utf-8'],
    ['.wasm', 'application/wasm'],
]);

function safePath(root, requestPath) {
    const relative = decodeURIComponent(requestPath).replace(/^\/+/, '');
    const resolved = path.resolve(root, relative);
    const base = path.resolve(root) + path.sep;
    return resolved.startsWith(base) ? resolved : null;
}

const pageOptions = JSON.stringify({ ...options, stdin }).replace(/</g, '\\u003c');
const html = `<!doctype html>
<meta charset="utf-8">
<script type="importmap">
{"imports":{"@bytecodealliance/preview2-shim":"/deps/dist/browser/index.js","@bytecodealliance/preview2-shim/":"/deps/dist/browser/"}}
</script>
<script>globalThis.TYPEPHP_WASM_TEST_OPTIONS = ${pageOptions};</script>
<script type="module" src="/harness.js"></script>`;

const harnessSource = `
import { _setStderr, _setStdin, _setStdout } from '/deps/dist/browser/cli.js';
import { _getPreopens, _setFileData, types as filesystemTypes } from '/deps/dist/browser/filesystem.js';
import { WASIShim } from '/deps/dist/common/instantiation.js';
import { instantiate } from '/artifact/program.js';

const decoder = new TextDecoder();
const encoder = new TextEncoder();
const options = globalThis.TYPEPHP_WASM_TEST_OPTIONS;
let output = '';
globalThis.TYPEPHP_WASM_TEST_STAGE = 'module-loaded';

function outputHandler() {
    return {
        write(bytes) {
            output += decoder.decode(bytes, { stream: true });
            return BigInt(bytes.byteLength);
        },
        blockingFlush() {},
    };
}

function inputHandler(text) {
    const bytes = encoder.encode(text);
    let offset = 0;
    return {
        blockingRead(length) {
            if (offset >= bytes.byteLength) throw { tag: 'closed' };
            const end = Math.min(offset + Number(length), bytes.byteLength);
            const value = bytes.slice(offset, end);
            offset = end;
            return value;
        },
    };
}

function installMutableFilesystem(fileData) {
    const descriptorEntries = new WeakMap();
    for (const [descriptor] of _getPreopens()) descriptorEntries.set(descriptor, fileData);

    function resolve(entry, guestPath) {
        for (const segment of String(guestPath).split('/')) {
            if (segment === '' || segment === '.') continue;
            if (segment === '..' || !entry?.dir?.[segment]) throw { tag: 'no-entry' };
            entry = entry.dir[segment];
        }
        return entry;
    }

    function remove(descriptor, guestPath, directory) {
        const root = descriptorEntries.get(descriptor);
        if (!root) throw { tag: 'bad-descriptor' };
        const segments = String(guestPath).split('/').filter((segment) => segment !== '' && segment !== '.');
        const name = segments.pop();
        if (!name || name === '..' || segments.includes('..')) throw { tag: 'no-entry' };
        const parent = resolve(root, segments.join('/'));
        const entry = parent?.dir?.[name];
        if (!entry) throw { tag: 'no-entry' };
        if (directory ? !entry.dir : entry.dir) throw { tag: directory ? 'not-directory' : 'is-directory' };
        if (directory && Object.keys(entry.dir).length !== 0) throw { tag: 'not-empty' };
        delete parent.dir[name];
    }

    const descriptor = filesystemTypes.Descriptor.prototype;
    const openAt = descriptor.openAt;
    descriptor.openAt = function (...args) {
        const opened = openAt.apply(this, args);
        const parent = descriptorEntries.get(this);
        if (parent) descriptorEntries.set(opened, resolve(parent, args[1]));
        return opened;
    };
    descriptor.unlinkFileAt = function (guestPath) {
        remove(this, guestPath, false);
    };
    descriptor.removeDirectoryAt = function (guestPath) {
        remove(this, guestPath, true);
    };
}

try {
    if (typeof WebAssembly.Suspending !== 'function' || typeof WebAssembly.promising !== 'function') {
        throw new Error('Chrome does not provide WebAssembly JSPI');
    }
    const fileData = { dir: { sandbox: { dir: {} } } };
    _setFileData(fileData);
    installMutableFilesystem(fileData);
    _setStdin(inputHandler(String(options.stdin || '')));
    _setStdout(outputHandler());
    _setStderr(outputHandler());
    const wasi = new WASIShim({ sandbox: {
        args: [String(options.argv0 || 'test.wasm'), ...(options.args || []).map(String)],
        env: { ...(options.env || {}) },
        enableNetwork: true,
    }});
    globalThis.TYPEPHP_WASM_TEST_STAGE = 'instantiating';
    const component = await instantiate(null, wasi.getImportObject());
    globalThis.TYPEPHP_WASM_TEST_STAGE = 'running';
    const result = await component.run.run();
    globalThis.TYPEPHP_WASM_TEST_RESULT = { output, result };
} catch (error) {
    if (error?.exitError && error.code === 0) {
        globalThis.TYPEPHP_WASM_TEST_RESULT = { output };
    } else {
        globalThis.TYPEPHP_WASM_TEST_RESULT = { output, error: error?.stack || String(error) };
    }
}
`;

const server = http.createServer(async (request, response) => {
    try {
        const url = new URL(request.url, 'http://127.0.0.1');
        if (url.pathname === '/') {
            response.writeHead(200, { 'content-type': 'text/html; charset=utf-8' });
            response.end(html);
            return;
        }
        if (url.pathname === '/harness.js') {
            response.writeHead(200, { 'content-type': 'text/javascript; charset=utf-8' });
            response.end(harnessSource);
            return;
        }
        const mapping = url.pathname.startsWith('/artifact/')
            ? [artifactDir, url.pathname.slice('/artifact/'.length)]
            : url.pathname.startsWith('/deps/')
                ? [shimDir, url.pathname.slice('/deps/'.length)]
                : null;
        if (!mapping) {
            response.writeHead(404).end();
            return;
        }
        let filename = safePath(mapping[0], mapping[1]);
        if (!filename) {
            response.writeHead(403).end();
            return;
        }
        let data;
        try {
            data = await fs.readFile(filename);
        } catch (error) {
            // The browser shim publishes Node-style extensionless ESM imports.
            // Resolve those within the already validated static root only.
            if (error?.code !== 'ENOENT' || path.extname(filename) !== '') throw error;
            filename += '.js';
            data = await fs.readFile(filename);
        }
        response.writeHead(200, { 'content-type': contentTypes.get(path.extname(filename)) || 'application/octet-stream' });
        response.end(data);
    } catch (error) {
        response.writeHead(error?.code === 'ENOENT' ? 404 : 500).end(String(error));
    }
});

await new Promise((resolve, reject) => {
    server.once('error', reject);
    server.listen(0, '127.0.0.1', resolve);
});

let browser;
try {
    const address = server.address();
    browser = await puppeteer.launch({
        executablePath: chromePath,
        headless: true,
        args: ['--no-sandbox', '--disable-dev-shm-usage', '--enable-features=WebAssemblyJSPI'],
    });
    const page = await browser.newPage();
    const diagnostics = [];
    page.on('console', (message) => diagnostics.push(`console: ${message.text()}`));
    page.on('pageerror', (error) => diagnostics.push(`pageerror: ${error.stack || error}`));
    page.on('requestfailed', (request) => diagnostics.push(
        `requestfailed: ${request.url()} (${request.failure()?.errorText || 'unknown error'})`
    ));
    await page.goto(`http://127.0.0.1:${address.port}/`, { waitUntil: 'load' });
    try {
        await page.waitForFunction(() => globalThis.TYPEPHP_WASM_TEST_RESULT !== undefined, { timeout: 120000 });
    } catch (error) {
        const stage = await page.evaluate(() => globalThis.TYPEPHP_WASM_TEST_STAGE || 'page-loading');
        throw new Error(`${error.message}\nstage: ${stage}\n${diagnostics.join('\n')}`);
    }
    const result = await page.evaluate(() => globalThis.TYPEPHP_WASM_TEST_RESULT);
    process.stdout.write(result.output || '');
    if (result.error) {
        process.stderr.write(result.error + '\n');
        process.exitCode = 1;
    }
} finally {
    await browser?.close();
    await new Promise((resolve) => server.close(resolve));
}
