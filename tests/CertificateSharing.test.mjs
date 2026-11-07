import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import ts from '../mobile/node_modules/typescript/lib/typescript.js';

const source = readFileSync(new URL('../mobile/src/certificates.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
function harness({ token = 'private-token', web = false, response, shareError } = {}) {
    const calls = [], writes = [], shares = [], deletes = [], links = [];
    class File {
        constructor(directory, name) { this.uri = `${directory}/${name}`; this.exists = false; }
        async write(bytes) { this.exists = true; writes.push({ uri: this.uri, bytes }); }
        delete() { this.exists = false; deletes.push(this.uri); }
    }
    const adapters = {
        'react-native': { Platform: { OS: web ? 'web' : 'android' } },
        './api/client': { API_URL: 'https://api.example.test/api/v1', getToken: async () => token },
        'expo-file-system': { File, Paths: { cache: 'file:///cache' } },
        'expo-sharing': { isAvailableAsync: async () => true, shareAsync: async (uri, options) => { shares.push({ uri, options }); if (shareError) throw new Error('Sharing unavailable'); } },
    };
    const module = { exports: {} };
    vm.runInNewContext(compiled, {
        module, exports: module.exports,
        require: name => { assert.ok(Object.hasOwn(adapters, name)); return adapters[name]; },
        fetch: async (url, options) => { calls.push({ url, ...options }); return response ?? new Response(new Uint8Array([137, 80, 78, 71]), { headers: { 'Content-Type': 'image/png' } }); },
        Uint8Array, AbortController, setTimeout: () => 0, clearTimeout() {},
        URL: { createObjectURL: () => 'blob:certificate', revokeObjectURL() {} },
        document: { createElement: () => { const link = { click() { links.push({ href: this.href, download: this.download }); } }; return link; } },
    });
    return { share: module.exports.shareCertificate, calls, writes, shares, deletes, links };
}

test('native certificate sharing authenticates the download and shares the PNG attachment', async () => {
    const { share, calls, writes, shares, deletes } = harness();
    await share(7, 'post');
    assert.equal(calls[0].url, 'https://api.example.test/api/v1/certificates/7/download?format=post');
    assert.equal(calls[0].headers.Authorization, 'Bearer private-token');
    assert.equal(writes[0].bytes.byteLength, 4);
    assert.equal(shares[0].options.mimeType, 'image/png');
    assert.equal(shares[0].options.UTI, 'public.png');
    assert.deepEqual(deletes, [shares[0].uri]);
});

test('PDF attachments use the PDF document type', async () => {
    const { share, shares } = harness({ response: new Response('%PDF-', { headers: { 'Content-Type': 'application/pdf' } }) });
    await share(3, 'pdf');
    assert.equal(shares[0].options.UTI, 'com.adobe.pdf');
    assert.ok(shares[0].uri.endsWith('.pdf'));
});

test('revoked or invalid files never reach the device share sheet', async () => {
    for (const response of [new Response('{}', { status: 410 }), new Response('<html>Login</html>', { headers: { 'Content-Type': 'text/html' } })]) {
        const { share, writes, shares } = harness({ response });
        await assert.rejects(share(4, 'post'));
        assert.equal(writes.length, 0);
        assert.equal(shares.length, 0);
    }
});

test('native sharing failures clean up downloaded certificate files', async () => {
    const { share, deletes } = harness({ shareError: true });
    await assert.rejects(share(5, 'story'));
    assert.equal(deletes.length, 1);
});

test('missing credentials do not start a certificate download', async () => {
    const { share, calls } = harness({ token: null });
    await assert.rejects(share(5, 'post'));
    assert.equal(calls.length, 0);
});

test('mobile browser downloads the image rather than passing a local URL to native sharing', async () => {
    const { share, links, shares } = harness({ web: true });
    await share(8, 'story');
    assert.equal(links[0].download, 'quran-certificate-8-story.png');
    assert.equal(shares.length, 0);
});
