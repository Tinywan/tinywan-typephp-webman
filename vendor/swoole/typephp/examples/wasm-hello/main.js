const storageName = 'typephp-wasi-demo-filesystem.json';
const elements = Object.fromEntries([
    'args', 'env', 'stdin', 'persistent', 'run', 'reset', 'status', 'output',
    'runtime-value', 'platform-value', 'clock-value', 'random-value', 'token-value',
    'filesystem-value', 'files-value', 'argv-value', 'env-value', 'stdin-value',
    'http-value', 'http-detail', 'bigint-value', 'decimal-value', 'bigfloat-value',
    'extension-count', 'extension-list',
].map((id) => [id, document.getElementById(id)]));

let worker = null;
let selectedExtension = '';

function parseArguments(source) {
    const args = [];
    const pattern = /"((?:\\.|[^"\\])*)"|'((?:\\.|[^'\\])*)'|([^\s]+)/g;
    for (const match of source.matchAll(pattern)) {
        args.push((match[1] ?? match[2] ?? match[3]).replace(/\\([\\"'])/g, '$1'));
    }
    return args;
}

function parseEnvironment(source) {
    return Object.fromEntries(source.split(/\r?\n/).flatMap((line) => {
        const separator = line.indexOf('=');
        return separator > 0 ? [[line.slice(0, separator).trim(), line.slice(separator + 1)]] : [];
    }));
}

function setStatus(kind, label) {
    elements.status.className = `status ${kind}`;
    elements.status.lastChild.textContent = label;
}

function value(id, content) {
    elements[id].textContent = content === '' ? '（空）' : String(content ?? '—');
}

function renderReport(report) {
    value('runtime-value', `PHP ${report.runtime.php}`);
    value('platform-value', report.runtime.platform);
    value('extension-count', `${report.runtime.extensions.length} 个内置扩展`);
    elements['extension-list'].replaceChildren(...report.runtime.extensions.map((extension) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'extension-button';
        button.dataset.extension = extension;
        button.textContent = extension;
        button.title = `查看 ${extension} 扩展信息`;
        return button;
    }));
    value('clock-value', report.clock.iso8601);
    value('random-value', report.random.integer);
    value('token-value', report.random.token);
    value('filesystem-value', `第 ${report.filesystem.run} 次运行`);
    value('files-value', report.filesystem.files.join(' · '));
    value('http-value', report.http.ok ? `${report.http.bytes} bytes` : '请求失败');
    value('http-detail', report.http.preview);
    value('argv-value', report.input.argv.join('  '));
    value('env-value', report.input.greeting);
    value('stdin-value', report.input.stdin);
    value('bigint-value', report.precision.bigint);
    value('decimal-value', report.precision.decimal);
    value('bigfloat-value', report.precision.bigfloat);
}

function appendCodeList(container, values, emptyLabel = '无') {
    if (!Array.isArray(values) || values.length === 0) {
        const empty = document.createElement('span');
        empty.textContent = emptyLabel;
        container.append(empty);
        return;
    }
    for (const item of values) {
        const code = document.createElement('code');
        code.textContent = String(item);
        code.title = String(item);
        container.append(code);
    }
}

function extensionGroup(title, content) {
    const group = document.createElement('section');
    group.className = 'extension-group';
    const heading = document.createElement('h4');
    heading.textContent = title;
    const items = document.createElement('div');
    items.className = 'extension-items';
    content(items);
    group.append(heading, items);
    return group;
}

function keyValueList(container, values) {
    const entries = values && typeof values === 'object' ? Object.entries(values) : [];
    if (entries.length === 0) {
        appendCodeList(container, []);
        return;
    }
    const list = document.createElement('dl');
    for (const [key, item] of entries) {
        const row = document.createElement('div');
        const term = document.createElement('dt');
        const value = document.createElement('dd');
        term.textContent = key;
        value.textContent = typeof item === 'string' ? item : JSON.stringify(item);
        row.append(term, value);
        list.append(row);
    }
    container.append(list);
}

function renderExtensionInfo(info) {
    const heading = document.createElement('div');
    heading.className = 'extension-detail-header';
    const identity = document.createElement('div');
    const name = document.createElement('h3');
    name.textContent = info.name;
    const version = document.createElement('p');
    version.textContent = `version ${info.version}`;
    identity.append(name, version);
    const flags = document.createElement('div');
    flags.className = 'extension-flags';
    for (const label of [info.persistent ? 'persistent' : 'non-persistent', info.temporary ? 'temporary' : 'built-in']) {
        const flag = document.createElement('span');
        flag.textContent = label;
        flags.append(flag);
    }
    heading.append(identity, flags);

    const groups = document.createElement('div');
    groups.className = 'extension-groups';
    groups.append(
        extensionGroup(`Functions · ${info.functions.length}`, (node) => appendCodeList(node, info.functions)),
        extensionGroup(`Classes · ${info.classes.length}`, (node) => appendCodeList(node, info.classes)),
        extensionGroup(`Constants · ${info.constants.length}`, (node) => appendCodeList(node, info.constants)),
        extensionGroup('INI entries', (node) => keyValueList(node, info.iniEntries)),
        extensionGroup('Dependencies', (node) => keyValueList(node, info.dependencies)),
    );
    document.getElementById('extension-detail').replaceChildren(heading, groups);
}

function loadExtensionInfo(extension) {
    if (!worker) return;
    selectedExtension = extension;
    for (const button of elements['extension-list'].querySelectorAll('.extension-button')) {
        button.classList.toggle('active', button.dataset.extension === extension);
    }
    document.getElementById('extension-detail').innerHTML = '<span class="extension-loading">正在调用 Wasm 导出函数…</span>';
    worker.postMessage({ type: 'extension-info', extension });
}

function run() {
    worker?.terminate();
    worker = new Worker(new URL('./typephp-worker.mjs', import.meta.url), { type: 'module' });
    let stdout = '';
    let stderr = '';
    selectedExtension = '';

    elements.run.disabled = true;
    elements.output.textContent = '正在实例化 WASI 0.2 Component…';
    setStatus('running', 'Running');

    worker.onmessage = ({ data }) => {
        if (data.type === 'stdout') {
            stdout += data.data;
        } else if (data.type === 'stderr') {
            stderr += data.data;
        } else if (data.type === 'error') {
            stderr += `${data.error}\n`;
            elements.run.disabled = false;
            setStatus('error', 'Wasm error');
            elements.output.textContent = [stdout, stderr].filter(Boolean).join('\n--- stderr ---\n');
        } else if (data.type === 'report') {
            elements.run.disabled = false;
            elements.output.textContent = [data.json, stdout, stderr].filter(Boolean).join('\n--- component output ---\n');
            try {
                renderReport(JSON.parse(data.json));
                setStatus('success', 'Ready for JS calls');
            } catch (error) {
                setStatus('error', 'Invalid export result');
                elements.output.textContent += `\n\nUI parse error: ${error.message}`;
            }
        } else if (data.type === 'extension-info') {
            if (data.extension === selectedExtension) {
                try {
                    renderExtensionInfo(JSON.parse(data.json));
                } catch (error) {
                    document.getElementById('extension-detail').textContent = `无法解析扩展信息：${error.message}`;
                }
            }
        } else if (data.type === 'extension-error' && data.extension === selectedExtension) {
            document.getElementById('extension-detail').textContent = data.error;
        }
    };

    worker.onerror = (event) => {
        elements.run.disabled = false;
        setStatus('error', 'Worker error');
        elements.output.textContent = event.message;
    };

    worker.postMessage({
        type: 'run',
        args: parseArguments(elements.args.value),
        env: parseEnvironment(elements.env.value),
        stdin: elements.stdin.value,
        persistent: elements.persistent.checked,
        storageName,
    });
}

async function resetStorage() {
    if (!navigator.storage?.getDirectory) {
        setStatus('error', 'OPFS unavailable');
        return;
    }
    const root = await navigator.storage.getDirectory();
    await root.removeEntry(storageName).catch((error) => {
        if (error.name !== 'NotFoundError') throw error;
    });
    setStatus('idle', 'Storage cleared');
    value('filesystem-value', '等待重新运行');
}

elements.run.addEventListener('click', run);
elements.reset.addEventListener('click', () => resetStorage().catch((error) => setStatus('error', error.message)));
elements['extension-list'].addEventListener('click', (event) => {
    const button = event.target.closest('.extension-button');
    if (button) loadExtensionInfo(button.dataset.extension);
});
run();
