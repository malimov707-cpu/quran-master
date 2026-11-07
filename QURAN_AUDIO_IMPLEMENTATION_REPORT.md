# Quran audio implementation report

Updated: 2026-11-07. This report distinguishes implemented behavior, current catalog inventory, earlier playback observations and outstanding device/provider verification. It does not certify every remote recording or physical device.

## Implemented

- Existing `quran_reciters` is reused. Audio editions contain source, permission reference, bitrate, style, version, manifest SHA-256 and catalog check date.
- Separate ayah/surah files reference canonical database IDs. Missing files are explicitly recorded, not replaced with placeholders. Canonical Quran text is not generated or changed.
- Authenticated REST and session-backed web endpoints provide catalog, playlists, validated preferences, private history, favorites, device download records and owned hifz lists.
- Web library and persistent mini-player support play/pause, previous/next ayah and surah, progress/time, 0.75/1/1.25/1.5/2× speed, volume, 1/3/5/10/20 repetitions, range looping and pauses for personal reading.
- The web audio element stays mounted during supported in-app GET navigation. Browser Media Session handlers are configured where available. A page reload or unsupported navigation may restart the document; browser/OS background policy still applies.
- Reader ayah highlighting follows individual file boundaries. The repository now also supports imported, verified full-surah alignment segments. Word timestamps are not fabricated. No alignment means no full-surah ayah synchronization.
- Expo global audio context persists across screen navigation, with mini-player/full player, range controls, history, favorites, hifz lists and local practice recording/comparison.
- `expo-file-system` 58.0.13 was added with explicit user approval. Native offline storage checks permitted editions, downloaded size and MP3 header, tracks real bytes, supports pause/resume state, keeps completed files during reconnect/retry, reports actual local completion and permits deletion.
- Native local practice recordings are not uploaded and are deleted when leaving their component. Existing authenticated assignment uploads remain separate.

## Provider review

Primary sources reviewed during implementation:

- [AlQuran.cloud CDN documentation](https://alquran.cloud/cdn): documented edition/bitrate/global-ayah and full-surah URLs and official file inventory.
- [AlQuran.cloud usage terms](https://alquran.cloud/terms-and-conditions): permits personal/educational streaming and downloads, with reciter copyrights retained and removal obligations. This is provider permission, not ownership of the recordings or an irrevocable blanket copyright license.
- [Quran Foundation developer terms](https://api-docs.quran.com/legal/developer-terms/): offline copies have Content Sync requirements; this provider was researched but not used as an unrestricted bulk-download source.
- [EveryAyah directory](https://everyayah.com/data/): researched; directory visibility alone was not treated as sufficient copyright permission.

An additional MP3Quran configuration/import branch is present among current workspace changes. Its new Yasser Al-Dosari edition is not counted as verified or available by this report; validate actual official catalog mapping and rights before publishing it.

## Current inventory and verification limits

The inspected database has 114 canonical surahs and the following seven imported editions:

| Edition | Provider ayah inventory | Selected quality |
| --- | ---: | ---: |
| Alafasy | 6236 / 6236 | 128 kbps |
| Abdul Basit Murattal | 6236 / 6236 | 192 kbps |
| Maher Al-Muaiqly | 6236 / 6236 | 128 kbps |
| Abdul Rahman Al-Sudais | 6236 / 6236 | 192 kbps |
| Husary | 6236 / 6236 | 64 kbps |
| Minshawi Murattal | 6235 / 6236 | 128 kbps |
| Minshawi Mujawwad | 6236 / 6236 | 64 kbps |

Minshawi Murattal's provider manifest lacks global ayah 3484. Husary's 128 kbps inventory lacked global ayah 4089, so the complete 64 kbps edition was selected. These observations are inventory checks, not listening certification of every recording. Saad Al-Ghamdi was not invented or substituted when absent from the selected provider inventory.

Only some editions have dedicated 114-file full-surah sets; the others can play a surah sequentially from ayah files. Selecting a missing dedicated file returns an honest empty/unavailable result.

`quran:audio:import --verify` probes bounded MP3 byte ranges and records HTTP status, length, ETag and check time. An earlier long verification run completed, but the current database snapshot no longer contains those full results: at this report's check, Alafasy has 25 `verified` ayah rows and 6211 `listed` rows; other present ayah rows remain `listed`. Therefore **all 6236 files are NOT claimed as currently individually verified**. A manifest-listed file is distinguished from a successfully probed file. Re-run verification against the final database before release; transport checks also do not replace full decoding and listening/identity review.

## Storage and endpoints

Tables: `quran_audio_editions`, `quran_audio_files`, `quran_audio_timestamps`, `quran_audio_preferences`, `quran_playback_histories`, `quran_audio_downloads`, `quran_audio_favorites`, `quran_audio_lists`. No duplicate reciter table was introduced.

API prefix `/api/v1/quran/audio`:

- `GET /` catalog; `GET /{edition}/{surah}` playlist/range/mode.
- `PUT /preferences`; `POST /history`; `POST /favorites/{edition}`.
- `POST /downloads`; `POST /lists`; `DELETE /lists/{list}`.

Web: `/quran/audio-library`, reader play controls, persistent mini-player and `/audio/...` session endpoints with CSRF. Mobile: `app/quran/audio.tsx`, `app/quran/player.tsx`, root provider and reader integration.

## Verification

- Audio API feature tests cover auth, licensing gates, canonical mapping, missing files, range validation, preference validation, private history, favorites, hifz ownership, download permission and source credits.
- Earlier Chromium playback checks observed real remote audio advancing, continuing across an in-app page change and playing through Expo web, with no recorded page exceptions or horizontal overflow. These are browser observations, not Android/iOS device certification.
- Latest TypeScript and production Vite build: PASS.
- The initial latest full backend run exposed three tests attempting live Whisper calls because local credentials leaked into test configuration; the provider returned 429. Test-only credentials are now forced empty and stray HTTP is blocked; dedicated provider tests must fake external responses. Production `.env` credentials were not changed.
- Final rerun: **239/239 backend tests PASS, 997 assertions**. Mobile TypeScript and production build also PASS.

## Acceptance status / remaining work

| Requirement | Status |
| --- | --- |
| 114-surah catalog and licensed source metadata | PASS for imported editions |
| Qori selection, ayah playback, sequential surah playback, history and preferences | Implemented; API tests and earlier browser playback PASS |
| Range, repeat, gap, favorites and owned hifz lists | Implemented; exhaustive native playback-state QA still needed |
| Every 6236 file individually transport/decode/identity verified | PARTIAL: official inventories checked; current stored probe coverage incomplete; full listening/decoding certification absent |
| Full-surah timestamp synchronization | PARTIAL: import/runtime support exists; complete licensed alignment data is not supplied for every edition |
| Offline download/pause/resume/deletion | Implemented for native; physical Android/iOS acceptance NOT TESTED; browser offline audio is explicitly unavailable |
| OS background playback and lock-screen controls | Configured; signed native builds and physical device/OS tests NOT TESTED |
| Practice microphone recording/comparison | Implemented locally; real-device microphone test NOT TESTED |
| Audio waveform | NOT IMPLEMENTED for the Quran player; no decorative fake waveform is shown |
| Shuffle | NOT IMPLEMENTED; canonical ayah order is preserved |
| Reciter profile biographies | Name/style/quality/availability provided; unverified biographical claims omitted |
| Production release | NOT READY for unconditional acceptance until the above checks are completed |

Do not equate a passing automated suite with complete audio coverage, licensing of an unreviewed source or physical-device verification.
