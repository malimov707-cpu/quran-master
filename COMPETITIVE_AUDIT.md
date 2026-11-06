# Competitive audit — Qur’on AI Ustoz

Audit started: 2026-11-05; updated: 2026-11-06. This is an audit of the existing repository, not a claim of production readiness.

## Existing architecture

Laravel 13.40 / PHP 8.4; Blade; Tailwind 4 / Vite 8. Expo 58 / React Native 0.88 / React 19.3 / TypeScript 6. Shared Sanctum REST API under `/api/v1`. No framework rewrite or dependency replacement is required.

**Environment discrepancy:** the inspected development database is SQLite, not PostgreSQL. Production PostgreSQL, Redis, S3/MinIO and a persistent queue worker must be provisioned and verified separately; their availability must not be inferred from configuration files. Backup/restore commands and health endpoint are present in current concurrent workspace changes; their operational restoration has not yet been proven by this audit.

## Existing modules

- RBAC: six roles, permission relationships, session auth for web and token auth for mobile.
- Academy: courses, groups, teacher/mentor assignments, published lessons/materials, schedules, attendance, assignments and submission history.
- Review: private authenticated audio endpoint; scores; resubmission; mentor comments and timed review segments; student notifications.
- AI: asynchronous recitation job, speech recognition contract/Whisper adapter, Quran text alignment, AI reviews/segments, confidence threshold and unavailable-tajweed provider.
- Progress: real grades, attendance, weekly summaries and mentor-confirmed practice recommendations.
- Parents: linked children only and per-child progress.
- Quran: source/checksum/import provenance, 114-surah/6236-ayah import validation, canonical Arabic text, offline mobile reader. Translation columns and reciter table exist, but this does not establish licensed populated translations/audio.
- UI: emerald/ivory/gold shared web shell and native mobile theme, language catalogs, RTL reader, local bookmarks/font controls, mentor waveform from real decoded audio, administration read-only data pages.

## Competitive research

Primary sources accessed 2026-11-05:

- [Tarteel](https://tarteel.ai/) describes memorization mistake detection for missed, incorrect and skipped words. Its [official tutorial](https://tarteel.ai/blog/tarteel-tutorials-memorization-mistake-detection/) describes mistake history. This is a product capability statement, not independent proof of model accuracy.
- [Quran.com learning plans](https://quran.com/learning-plans) provide guided lessons and tracked progress, alongside reading, translation and listening tools.

Product direction inferred from these sources: combine structured revision and clear reading/navigation with the repository’s existing mentor, parent and center workflows. No claim that our ASR exceeds these products is supported without a labelled evaluation corpus.

## Gaps and findings

| Area | Finding / required action |
| --- | --- |
| Student learning path | Existing recommendations are a list, not a persistent adaptive revision schedule. Implement authenticated daily plan, explicit self-assessment history, and due dates. |
| Error map | Keep AI hypotheses separate from mentor-confirmed segments. Add real 30-day breakdown and never treat self-assessment as mentor certification. |
| Authorization | Several mentor endpoints accept any reviewer permission without checking assigned group. Centralize the reviewer scope across queue, detail, AI, audio and write paths. Existing assignment fixture creates an unassigned group; correct the fixture to model a legitimately assigned mentor, without weakening assertions. |
| Web education | Add missing permission checks on course/group management endpoints and hide creation forms when permission is absent. |
| ASR/AI | Live external provider call and accuracy benchmark are unverified. Realtime streaming, religious Q&A and trained tajweed analysis are not implemented. No synthetic AI result should be substituted. |
| Quran resources | Licensed Uzbek/Russian translation import, reciter audio and aligned tajweed annotations require confirmed content/provider sources. |
| Privacy | Retention/deletion, guardian consent and center policy require an explicit product/legal policy. Existing audio auth alone does not satisfy this requirement. |
| Mobile | Expo web preview works; physical Android/iOS microphone, background playback, push credentials and signed builds remain untested. |
| Live lessons | No configured video provider. Do not expose a fake “join” action. |
| Analytics | Current real counts, attendance/registration charts and group capacity are useful; filtered effectiveness reports and measured performance benchmark remain incomplete. |
| Gamification | Goals, achievements, certificates and streaks need persisted rules. Do not award religious mastery from self-report. |
| Localization | Shared locale catalogs exist; full dynamic validation/error/date localization still needs a completeness pass. |
| Visuals | Earlier generated courtyard is an illustration, not the Islamic Civilization Center. Replace active background with an actual licensed photograph and give attribution. |

## Verification baseline and next deliverable

The existing 188-test backend suite passed during the UI work; five interface tests then passed (32 assertions). Seven offline-cache tests and mobile TypeScript passed. Chromium exercised 90 web route/viewport combinations and found a 768px topbar overflow and unlabeled legacy course/group fields; fixes have been applied and require the final repeat. Do not interpret these checks as Android/iOS device certification.

Immediate implementation: Phase 2 authenticated learning path, distinct error map and persisted spaced repetition; repair the identified authorization boundaries; finalize UI regression checks. Remaining external integrations must retain honest BLOCKED / NOT IMPLEMENTED statuses.

## Delivery follow-up

The scoped revision/error-map slice and authorization/confidence fixes are implemented. Final suite: 208 tests / 883 assertions PASS. See `COMPETITIVE_TRANSFORMATION_REPORT.md` for exact statuses and remaining acceptance work.
