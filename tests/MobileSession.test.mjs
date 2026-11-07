import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import ts from '../mobile/node_modules/typescript/lib/typescript.js';

const source = readFileSync(new URL('../mobile/src/api/client.ts', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, esModuleInterop: true } }).outputText;
const tick = () => new Promise(resolve => setImmediate(resolve));
const deferred = () => {
  let resolve;
  const promise = new Promise(done => { resolve = done; });
  return { promise, resolve };
};

function clientHarness({ fetch, native = false, secure = {}, timer } = {}) {
  const storage = new Map();
  const module = { exports: {} };
  const adapters = {
    'expo-constants': { expoConfig: { extra: { apiUrl: 'https://api.example.test/v1' } } },
    'react-native': { Platform: { OS: native ? 'ios' : 'web' } },
    'expo-secure-store': {
      getItemAsync: async key => storage.get(key) ?? null,
      setItemAsync: async (key, value) => { storage.set(key, value); },
      deleteItemAsync: async key => { storage.delete(key); },
      ...secure,
    },
  };
  const context = {
    module, exports: module.exports,
    require: name => { assert.ok(Object.hasOwn(adapters, name), `Unexpected dependency: ${name}`); return adapters[name]; },
    process: { env: {} },
    window: { sessionStorage: { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) } },
    fetch: fetch ?? (async () => new Response('{}')), FormData, AbortController,
    setTimeout: timer ?? setTimeout, clearTimeout,
  };
  vm.runInNewContext(compiled, context, { filename: 'mobile/src/api/client.ts' });
  return { client: module.exports, storage };
}

test('an active session receiving 401 clears its persisted token and notifies once', async () => {
  const { client, storage } = clientHarness({ fetch: async () => new Response('{}', { status: 401 }) });
  await client.setToken('session-a');
  let expired = 0;
  client.onSessionExpired(() => { expired++; });
  await assert.rejects(client.api.get('/private'), error => error.status === 401);
  assert.equal(await client.getToken(), null);
  assert.equal(storage.has('auth_token'), false);
  assert.equal(expired, 1);
});

test('a delayed 401 from a previous session cannot erase a new login', async () => {
  const response = deferred();
  const { client, storage } = clientHarness({ fetch: () => response.promise });
  await client.setToken('session-a');
  let expired = 0;
  client.onSessionExpired(() => { expired++; });
  const oldRequest = client.api.get('/private');
  await tick();
  await client.setToken('session-b');
  response.resolve(new Response('{}', { status: 401 }));
  await assert.rejects(oldRequest, error => error.status === 401);
  assert.equal(await client.getToken(), 'session-b');
  assert.equal(storage.get('auth_token'), 'session-b');
  assert.equal(expired, 0);
});

test('simultaneous unauthorized requests expire the session only once', async () => {
  const responses = [deferred(), deferred()];
  let count = 0;
  const { client } = clientHarness({ fetch: () => responses[count++].promise });
  await client.setToken('session-a');
  let expired = 0;
  const unsubscribe = client.onSessionExpired(() => { expired++; });
  const requests = [client.api.get('/one'), client.api.get('/two')];
  await tick();
  responses.forEach(response => response.resolve(new Response('{}', { status: 401 })));
  const outcomes = await Promise.allSettled(requests);
  assert.ok(outcomes.every(outcome => outcome.status === 'rejected' && outcome.reason.status === 401));
  assert.equal(expired, 1);
  unsubscribe();
});

test('revoking an old token never expires the currently logged in account', async () => {
  const response = deferred();
  let authorization;
  const { client } = clientHarness({ fetch: (_url, options) => { authorization = options.headers.Authorization; return response.promise; } });
  await client.setToken('session-b');
  let expired = 0;
  client.onSessionExpired(() => { expired++; });
  const revoke = client.revokeToken('session-a');
  response.resolve(new Response('{}', { status: 401 }));
  await revoke;
  assert.equal(authorization, 'Bearer session-a');
  assert.equal(await client.getToken(), 'session-b');
  assert.equal(expired, 0);
});

test('a request timeout reports a retryable error without clearing credentials', async () => {
  let fireTimeout;
  const { client } = clientHarness({
    timer: (callback, delay) => { assert.equal(delay, 20000); fireTimeout = callback; return undefined; },
    fetch: (_url, options) => new Promise((_resolve, reject) => { options.signal.addEventListener('abort', () => reject(new Error('Aborted')), { once: true }); }),
  });
  await client.setToken('session-a');
  const request = client.api.get('/private');
  await tick();
  fireTimeout();
  await assert.rejects(request, error => error.status === 408 && error.message.includes('Qayta urinib'));
  assert.equal(await client.getToken(), 'session-a');
});

test('offline requests preserve the session and return a connection message', async () => {
  const { client } = clientHarness({ fetch: async () => { throw new TypeError('Network request failed'); } });
  await client.setToken('session-a');
  await assert.rejects(client.api.get('/private'), error => error.status === 0 && error.message.includes('Internet'));
  assert.equal(await client.getToken(), 'session-a');
});

test('native token writes stay ordered across rapid sign in and sign out', async () => {
  const gate = deferred();
  const calls = [];
  const { client } = clientHarness({ native: true, secure: {
    setItemAsync: async (_key, value) => { calls.push(value); if (value === 'session-a') await gate.promise; },
    deleteItemAsync: async () => { calls.push(null); },
  } });
  const first = client.setToken('session-a');
  const clear = client.setToken(null);
  const second = client.setToken('session-b');
  await tick();
  assert.deepEqual(calls, ['session-a']);
  assert.equal(await client.getToken(), 'session-b');
  gate.resolve();
  await Promise.all([first, clear, second]);
  assert.deepEqual(calls, ['session-a', null, 'session-b']);
});

test('a slow native storage read cannot overwrite a more recent token', async () => {
  const read = deferred();
  const { client } = clientHarness({ native: true, secure: { getItemAsync: () => read.promise } });
  const initialRead = client.getToken();
  await client.setToken('session-b');
  read.resolve('obsolete-session');
  assert.equal(await initialRead, 'session-b');
});

test('caller cancellation stays a cancellation and keeps the session', async () => {
  const external = new AbortController();
  const { client } = clientHarness({ fetch: (_url, options) => new Promise((_resolve, reject) => {
    options.signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')), { once: true });
  }) });
  await client.setToken('session-a');
  const request = client.api.get('/private', { signal: external.signal });
  await tick();
  external.abort();
  await assert.rejects(request, error => error.name === 'AbortError' && error.status === undefined);
  assert.equal(await client.getToken(), 'session-a');
});

test('audio uploads receive a longer deadline than ordinary JSON requests', async () => {
  let deadline;
  const { client } = clientHarness({ timer: (_callback, delay) => { deadline = delay; return undefined; }, fetch: async () => new Response('{"accepted":true}', { status: 201 }) });
  const form = new FormData();
  form.append('audio', new Blob(['audio'], { type: 'audio/wav' }), 'practice.wav');
  const result = await client.api.post('/assignments/1/submit', form);
  assert.equal(result.accepted, true);
  assert.equal(deadline, 120000);
});
