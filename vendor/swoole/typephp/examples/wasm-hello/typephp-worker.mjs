import {
    _setStderr,
    _setStdin,
    _setStdout,
} from '@bytecodealliance/preview2-shim/cli';
import {
    _getPreopens,
    _setFileData,
    types as filesystemTypes,
} from '@bytecodealliance/preview2-shim/filesystem';
import { WASIShim } from '@bytecodealliance/preview2-shim/instantiation';

const encoder = new TextEncoder();
const decoder = new TextDecoder();
let runtime = null;
let fileData = null;
let persistent = false;
let storageName = 'typephp-wasi-filesystem.json';
let extensionQueue = Promise.resolve();

function installMutableFilesystem(data) {
    const descriptorEntries = new WeakMap();
    for (const [descriptor] of _getPreopens()) descriptorEntries.set(descriptor, data);

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

function outputHandler(stream) {
    return {
        write(bytes) {
            self.postMessage({ type: stream, data: decoder.decode(bytes, { stream: true }) });
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
            if (offset >= bytes.byteLength) {
                throw { tag: 'closed' };
            }
            const end = Math.min(offset + Number(length), bytes.byteLength);
            const chunk = bytes.slice(offset, end);
            offset = end;
            return chunk;
        },
    };
}

function encodeFileData(value) {
    return JSON.stringify(value, (_key, item) => item instanceof Uint8Array
        ? { typephpBytes: Array.from(item) }
        : item);
}

function decodeFileData(value) {
    return JSON.parse(value, (_key, item) => item && Array.isArray(item.typephpBytes)
        ? new Uint8Array(item.typephpBytes)
        : item);
}

async function openPersistentFile(name) {
    if (!navigator.storage?.getDirectory) {
        throw new Error('OPFS is not available in this browser');
    }
    const root = await navigator.storage.getDirectory();
    const handle = await root.getFileHandle(name, { create: true });
    if (typeof handle.createSyncAccessHandle !== 'function') {
        throw new Error('OPFS synchronous access requires a dedicated Worker');
    }
    return handle.createSyncAccessHandle();
}

async function loadFileData(storageName) {
    const access = await openPersistentFile(storageName);
    try {
        const size = access.getSize();
        if (size === 0) {
            return { dir: {} };
        }
        const bytes = new Uint8Array(size);
        access.read(bytes, { at: 0 });
        return decodeFileData(decoder.decode(bytes));
    } finally {
        access.close();
    }
}

async function saveFileData(storageName, fileData) {
    const access = await openPersistentFile(storageName);
    try {
        const bytes = encoder.encode(encodeFileData(fileData));
        access.truncate(0);
        access.write(bytes, { at: 0 });
        access.flush();
    } finally {
        access.close();
    }
}

async function start(data) {
    try {
        if (typeof WebAssembly.Suspending !== 'function'
            || typeof WebAssembly.promising !== 'function') {
            throw new Error('This browser does not support WebAssembly JSPI, which is required for blocking WASI I/O');
        }
        persistent = data.persistent === true;
        storageName = String(data.storageName || 'typephp-wasi-filesystem.json');
        fileData = persistent ? await loadFileData(storageName) : { dir: {} };
        _setFileData(fileData);
        installMutableFilesystem(fileData);
        _setStdin(inputHandler(String(data.stdin || '')));
        _setStdout(outputHandler('stdout'));
        _setStderr(outputHandler('stderr'));

        const args = ['typephp.wasm', ...(Array.isArray(data.args) ? data.args.map(String) : [])];
        const env = data.env && typeof data.env === 'object' ? { ...data.env } : {};
        env.TYPEPHP_FETCH_URL ??= new URL('/fetch-demo.json', self.location.href).href;
        const wasi = new WASIShim({
            sandbox: {
                args,
                env,
                enableNetwork: true,
            },
        });
        const { instantiate } = await import('./generated/program.js');
        const component = await instantiate(null, wasi.getImportObject());
        runtime = await component.api.createRuntime();
        const json = await runtime.getDemoReport(
            JSON.stringify(Array.isArray(data.args) ? data.args.map(String) : []),
            String(env.DEMO_GREETING || ''),
            String(data.stdin || ''),
        );
        if (persistent) {
            await saveFileData(storageName, fileData);
        }
        self.postMessage({ type: 'report', json });
    } catch (error) {
        self.postMessage({ type: 'error', error: error?.stack || String(error) });
    }
}

async function getExtensionInfo(extension) {
    if (!runtime) {
        throw new Error('TypePHP runtime is not ready');
    }
    const json = await runtime.getExtensionInfo(extension);
    self.postMessage({ type: 'extension-info', extension, json });
}

self.onmessage = ({ data }) => {
    if (data?.type === 'run') {
        start(data);
    } else if (data?.type === 'extension-info') {
        const extension = String(data.extension || '');
        extensionQueue = extensionQueue
            .then(() => getExtensionInfo(extension))
            .catch((error) => {
                self.postMessage({ type: 'extension-error', extension, error: error?.stack || String(error) });
            });
    }
};
