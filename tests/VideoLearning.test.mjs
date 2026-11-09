import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import ts from '../mobile/node_modules/typescript/lib/typescript.js';
import { mergeVideoRanges as browserMerge } from '../public/js/lesson-videos.js';
const compiled = file => ts.transpileModule(readFileSync(new URL(file, import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, esModuleInterop: true } }).outputText;
const sources = { watch: compiled('../mobile/src/video-progress.ts'), api: compiled('../mobile/src/api/lesson-videos.ts'), offline: compiled('../mobile/src/video-offline.ts') };
const tick = () => new Promise(resolve => setImmediate(resolve));
const futureURL = 'https://api.example.test/lesson-video/10/7?version=version1&download=1&expires=4102444800&signature=limited-download';
const video = { id: 10, lesson_id: 3, title: 'Tajvid', provider: 'upload', version: 'version1', duration_seconds: 100, size_bytes: 64, offline_allowed: true, download_url: futureURL, url: futureURL, progress: { position_seconds: 0, ranges: [], observed_at: null } };
function harness({ deferredDownload = false } = {}) {
    const storage = new Map(), secure = new Map(), files = new Map(), calls = [], tasks = [];
    const control = { token: 'private-session-token', online: true, postGate: null, deferredDownload, restored: 0 };
    const memory = { getItem: async key => storage.get(key) ?? null, setItem: async (key, value) => { storage.set(key, value); }, removeItem: async key => { storage.delete(key); }, getAllKeys: async () => [...storage.keys()] };
    class File {
        constructor(directory, name) { this.uri = name ? `${directory}/${name}` : directory; }
        get exists() { return files.has(this.uri); }
        get size() { return files.get(this.uri)?.byteLength ?? 0; }
        delete() { files.delete(this.uri); }
        async write(bytes) { files.set(this.uri, bytes); }
        open() { return { readBytes: async count => files.get(this.uri).slice(0, count), close() {} }; }
        static createDownloadTask(url, file, options) { return new Task(url, file, options); }
    }
    class Task {
        constructor(url, file, options) { this.url = url; this.file = file; this.options = options; this.state = 'idle'; tasks.push(this); }
        start() {
            this.state = 'active';
            return new Promise(resolve => { this.resolve = resolve; if (!control.deferredDownload) queueMicrotask(() => this.complete()); });
        }
        complete() { const data = new Uint8Array(64); data.set([102, 116, 121, 112], 4); files.set(this.file.uri, data); this.state = 'completed'; this.options.onProgress({ bytesWritten: 64, totalBytes: 64 }); this.resolve(this.file); }
        downloadAsync() { return this.start(); }
        resumeAsync() { return this.start(); }
        async pauseAsync() { this.state = 'paused'; this.resolve(null); }
        savable() { return { url: this.url, fileUri: this.file.uri, isDirectory: false, resumeData: 'opaque-native-state', headers: { Authorization: 'must-not-persist' } }; }
        release() { this.released = true; }
        static fromSavable(state, options) { control.restored++; return new Task(state.url, new File(state.fileUri), options); }
    }
    const network = {
        get: async path => { calls.push({ method: 'get', path }); return { user_id: 7, videos: [video] }; },
        post: async (path, body) => { calls.push({ method: 'post', path, body }); if (control.postGate) await control.postGate; return {}; },
        delete: async path => { calls.push({ method: 'delete', path }); return {}; },
    };
    const modules = {
        'react-native': { Platform: { OS: 'android' } },
        '@react-native-async-storage/async-storage': memory,
        '@react-native-community/netinfo': { fetch: async () => ({ isConnected: control.online, isInternetReachable: control.online }) },
        'expo-file-system': { File, Paths: { document: 'file:///documents', cache: 'file:///cache' }, DownloadTask: Task },
        'expo-secure-store': { getItemAsync: async key => secure.get(key) ?? null, setItemAsync: async (key, value) => { secure.set(key, value); }, deleteItemAsync: async key => { secure.delete(key); } },
        './client': { api: network, API_URL: 'https://api.example.test/api/v1', getToken: async () => control.token },
    };
    const run = name => {
        const module = { exports: {} };
        vm.runInNewContext(sources[name], { module, exports: module.exports, require: key => { assert.ok(Object.hasOwn(modules, key), `Unexpected module: ${key}`); return modules[key]; }, URL, FormData, fetch, Uint8Array });
        return module.exports;
    };
    const watch = run('watch'); modules['../video-progress'] = watch;
    const api = run('api'); modules['./api/lesson-videos'] = api; modules['./api/client'] = modules['./client'];
    return { watch, api, offline: run('offline'), storage, secure, files, calls, tasks, control };
}

test('watch coverage ignores seeking and counts repeated sections once', () => {
    const { watch } = harness();
    const tracker = new watch.VideoWatchTracker(100, { position_seconds: 0, ranges: [], observed_at: null });
    tracker.sample(0, true, 1000); tracker.sample(1, true, 2000); tracker.sample(2, true, 3000);
    tracker.sample(99, true, 4000);
    assert.equal(tracker.percent(), 2);
    tracker.sample(0, true, 5000); tracker.sample(1, true, 6000);
    assert.equal(tracker.percent(), 2);
    tracker.sample(50, false, 7000); tracker.sample(51, false, 8000);
    assert.equal(tracker.percent(), 2);
});

test('continuous playback and browser range merging agree on the viewed timeline', () => {
    const { watch } = harness();
    const tracker = new watch.VideoWatchTracker(100, { position_seconds: 0, ranges: [], observed_at: null });
    for (let index = 0; index <= 90; index++) tracker.sample(index, true, 1000 + index * 1000);
    assert.equal(tracker.percent(), 90);
    assert.equal(tracker.snapshot().position_seconds, 90);
    const ranges = [[30, 40], [0, 10], [5, 30]];
    assert.deepEqual(JSON.parse(JSON.stringify(watch.mergeVideoRanges(ranges))), browserMerge(ranges));
});

test('offline watch records keep the latest resume point and merge coverage on reconnect', async () => {
    const { api, control, calls } = harness();
    control.online = false;
    await api.saveVideoWatch(7, video, { position_seconds: 80, ranges: [[70, 80]], observed_at: '2026-11-08T10:00:00Z' });
    await api.saveVideoWatch(7, video, { position_seconds: 20, ranges: [[0, 20]], observed_at: '2026-11-07T10:00:00Z' });
    assert.equal((await api.loadVideoWatch(7, video)).position_seconds, 80);
    await api.syncVideoWatch(7); assert.equal(calls.length, 0);
    control.online = true; await api.syncVideoWatch(7);
    assert.equal(calls[0].body.owner_id, 7);
    assert.equal(calls[0].body.position_seconds, 80);
    assert.equal((await api.loadVideoWatch(8, video)).position_seconds, 0);
});

test('a delayed sync response cannot overwrite newer offline progress', async () => {
    const { api, control, storage, calls } = harness();
    await api.saveVideoWatch(7, video, { position_seconds: 10, ranges: [[0, 10]], observed_at: '2026-11-08T10:00:00Z' });
    let release; control.postGate = new Promise(resolve => { release = resolve; });
    const sync = api.syncVideoWatch(7); while (!calls.length) await tick();
    await api.saveVideoWatch(7, video, { position_seconds: 30, ranges: [[10, 30]], observed_at: '2026-11-08T10:01:00Z' });
    release(); await sync;
    const saved = JSON.parse([...storage.values()][0]);
    assert.equal(saved.watch.position_seconds, 30); assert.equal(saved.dirty, true);
});

test('native video downloads validate complete MP4 files and isolate accounts', async () => {
    const { offline, files } = harness();
    assert.equal(await offline.downloadVideo(7, video), true);
    assert.ok(await offline.offlineVideoUri(7, video));
    assert.equal(await offline.offlineVideoUri(8, video), null);
    assert.equal((await offline.offlineVideos(8)).length, 0);
    await offline.deleteOfflineVideo(7, video);
    assert.equal(files.size, 0);
});

test('pause and resume persist transfer state without API authorization headers', async () => {
    const { offline, storage, tasks, control } = harness({ deferredDownload: true });
    const first = offline.downloadVideo(7, video);
    while (!tasks.length || tasks[0].state !== 'active') await tick();
    await offline.pauseVideoDownload(7);
    assert.equal(await first, false);
    const saved = [...storage.values()].join('');
    assert.ok(!saved.includes('must-not-persist'));
    assert.ok(!saved.includes(control.token));
    assert.equal((await offline.pausedVideos(7))[0].id, 10);
    control.deferredDownload = false;
    assert.equal(await offline.downloadVideo(7, video), true);
    assert.equal(control.restored, 1);
});

test('offline ownership is bound to the secure session without restoring cached permissions', async () => {
    const { offline, control } = harness();
    await offline.rememberOfflineVideoOwner(7, 'Student', control.token);
    const owner = await offline.offlineVideoOwner();
    assert.deepEqual(Object.keys(owner).sort(), ['id', 'name']);
    assert.equal(owner.id, 7);
    control.token = 'different-account-session'; assert.equal(await offline.offlineVideoOwner(), null);
    await offline.clearOfflineVideoOwner(); assert.equal(await offline.offlineVideoOwner(), null);
});

test('YouTube and disabled videos never start an offline file transfer', async () => {
    const { offline, tasks } = harness();
    await assert.rejects(offline.downloadVideo(7, { ...video, provider: 'youtube' }));
    await assert.rejects(offline.downloadVideo(7, { ...video, offline_allowed: false }));
    assert.equal(tasks.length, 0);
});
