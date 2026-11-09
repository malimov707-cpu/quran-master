import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import ts from '../mobile/node_modules/typescript/lib/typescript.js';
const source = ts.transpileModule(readFileSync(new URL('../mobile/src/stores/auth.tsx', import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.ReactJSX, esModuleInterop: true } }).outputText;
const tick = () => new Promise(resolve => setImmediate(resolve));
function harness() {
    const effects = [], updates = [[], []], calls = [], identity = [];
    const config = { online: false, token: 'existing-session', getGate: null, listener: null, expired: null, context: null };
    let stateIndex = 0;
    const user = { id: 7, name: 'Server user', email: 'a@example.test', roles: ['STUDENT'], permissions: ['lessons.view'] };
    const modules = {
        react: { createContext: () => ({ Provider: 'Provider' }), useState: initial => { const index = stateIndex++; return [initial, value => updates[index].push(value)]; }, useEffect: callback => { effects.push(callback); }, useRef: current => ({ current }), useContext: () => config.context },
        'react/jsx-runtime': { jsx: (_, props) => { config.context = props.value; return props; } },
        '@tanstack/react-query': { useQueryClient: () => ({ clear() {} }) },
        '@react-native-async-storage/async-storage': {},
        '@react-native-community/netinfo': { addEventListener: callback => { config.listener = callback; return () => {}; }, fetch: async () => ({ isConnected: config.online, isInternetReachable: config.online }) },
        '../video-offline': { rememberOfflineVideoOwner: async (id, name) => { identity.push({ action: 'remember', id }); }, clearOfflineVideoOwner: async () => { identity.push({ action: 'clear' }); }, pauseCurrentVideoDownload: async () => { identity.push({ action: 'pause' }); } },
        '../api/client': { getToken: async () => config.token, setToken: async token => { config.token = token; }, revokeToken: async () => {}, onSessionExpired: callback => { config.expired = callback; return () => {}; }, api: { get: async () => { calls.push('auth/me'); if (config.getGate) await config.getGate; return user; }, post: async () => ({ token: 'new-session', user: { ...user, id: 8, name: 'New account' } }) } },
    };
    const module = { exports: {} };
    vm.runInNewContext(source, { module, exports: module.exports, require: key => { assert.ok(Object.hasOwn(modules, key), key); return modules[key]; } });
    module.exports.AuthProvider({ children: null }); effects.forEach(effect => effect());
    return { config, calls, identity, updates };
}

test('offline cold start does not request server data or restore cached role permissions', async () => {
    const { calls, updates, identity } = harness(); await tick();
    assert.equal(calls.length, 0);
    assert.equal(updates[0].length, 0);
    assert.equal(updates[1].at(-1), false);
    assert.equal(identity.length, 0);
});

test('reconnection restores the authenticated server identity', async () => {
    const { config, calls, updates, identity } = harness(); await tick();
    config.online = true; config.listener({ isConnected: true, isInternetReachable: true }); await tick();
    assert.equal(calls.length, 1);
    assert.equal(updates[0].at(-1).id, 7);
    assert.equal(identity.at(-1).action, 'remember');
});

test('a stale reconnect response cannot replace a newly signed in account', async () => {
    const { config, updates } = harness(); await tick();
    let resolve; config.getGate = new Promise(done => { resolve = done; });
    config.listener({ isConnected: true, isInternetReachable: true }); await tick();
    await config.context.login('new@example.test', 'password');
    resolve(); await tick();
    assert.equal(updates[0].at(-1).id, 8);
    assert.equal(config.token, 'new-session');
});

test('session expiration pauses transfers and removes offline identity without granting access', async () => {
    const { config, updates, identity } = harness(); await tick(); config.expired(); await tick();
    assert.equal(updates[0].at(-1), null);
    assert.deepEqual(identity.map(item => item.action), ['pause', 'clear']);
});
