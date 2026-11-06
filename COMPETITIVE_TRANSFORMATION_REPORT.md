# Competitive transformation — incremental delivery

Updated: 2026-11-06. **The complete eight-phase specification is not finished.** This delivery completes the repository audit, implements a usable first learning-path/revision slice, repairs authorization gaps and strengthens confidence handling. `COMPETITIVE_AUDIT.md` records the starting state and primary competitive sources; `UI_TRANSFORMATION_REPORT.md` records the wider UI changes.

## Status definitions

PASS = the stated scoped behavior works and passed the cited checks. PARTIAL = some requested behavior exists. BLOCKED = a real external service/content credential or agreement is required. NOT IMPLEMENTED = absent. NOT TESTED = no verification of the stated environment/behavior.

## Implemented changes

| Capability | Status | Evidence / practical limit |
| --- | --- | --- |
| Existing architecture retained | PASS | Laravel/Blade/Tailwind and Expo use the same `/api/v1`; no new application dependency or replacement framework. |
| Private student learning plan | PASS | Authenticated student-only API/web/Expo view; real due/upcoming cards, pending enrolled-group assignments, weekly practice count. Other users cannot access revision records. |
| Persistent spaced repetition | PASS | Canonical ayah reference; repeat/hard/good/easy ratings; adaptive intervals and due dates; transaction, version check and unique review version stop duplicate submissions. |
| Revision history | PASS | Ratings and assigned intervals are persisted separately from mentor scores. These are explicitly self-reports, not proof of memorization. |
| Complete individual development passport | PARTIAL | Grades, attendance and mentor practice recommendations already exist; integrated letter skills, speed measurement, goal editor and full mastery history remain absent. |
| 30-day error map | PASS | Separate aggregated mentor segments and AI hypotheses; only the current student's submissions. AI hypotheses are never shown as mentor-confirmed. |
| Full error trend/letter map | PARTIAL | Current map groups actual surah/ayah/error occurrences. Letter-specific assessment and before/after trend charts are not implemented. |
| Mentor submission access | PASS | Shared scope checks group mentor/teacher assignment, with report-permission administration access. Applied to API details, queue, audio, AI results and API/web grading/studio. Owner still has own audio access. |
| Web education management permissions | PASS | Added explicit course/group read/write permission checks; students cannot see course creation controls or mutate management data. |
| ASR confidence hardening | PASS | Zero confidence is retained. Missing/out-of-range confidence no longer becomes a fabricated confidence score from text similarity; uncertain results require mentor review. Real provider accuracy remains unverified. |
| AI provider status screen | PASS | Uses the configured provider's `isConfigured()` contract, not a mismatched configuration key. Configuration does not claim a successful live connection. |
| Canonical Quran integrity | PASS | Reader/enrolment reference existing stored text; no Quran generation or rewriting. Live read-only count: 114 surahs, 6236 ayahs. Existing import provenance/checksum validation retained. |
| Licensed actual building background | PASS | Real Islamic Civilization Center photograph, local 1920px/800px copies, gradient/solid fallback, source and CC BY-SA 4.0 credit. Generated courtyard illustration removed. |
| Native learning plan screen | PARTIAL | Expo web preview and TypeScript passed; physical Android/iOS builds and microphone runs remain NOT TESTED. |
| Full multilingual experience | PARTIAL | Static controls/new learning views and navigation support Uzbek Latin/Cyrillic/Russian. Stored bodies, every dynamic validation/date/unit are not comprehensively localized. |

## Schema changes

Additive migrations only; applied successfully to the existing local SQLite database. No reset or production seeding was run.

- `repetition_cards`: user/ayah foreign keys, unique user+ayah, indexed user+due_at, interval, successful-review count, last rating/time and optimistic version.
- `repetition_reviews`: card foreign key, self-rating, interval and review timestamp, unique card+version.
- Useful factories accompany both models. No fabricated Quran/content seeder was introduced.

The interval policy is a bounded deterministic heuristic: “again” schedules 10 minutes, “hard” 1 day, successive “good” ratings use 1/3/doubling, and “easy” increases faster. This is not a trained AI model, a calibrated memory estimate or a religious proficiency certificate. Every review requires the owned card's current version and a due date that has arrived.

## Added endpoints and screens

| Surface | Routes |
| --- | --- |
| API | `GET /api/v1/learning-path`, `POST /api/v1/learning-path/cards`, `POST /api/v1/learning-path/cards/{card}/review` |
| Web | `GET /learning-path`, `POST /learning-path/cards`, `POST /learning-path/cards/{card}/review` |
| Expo Android/iOS shared screen | `mobile/app/(tabs)/learning.tsx`; student-only Reja tab and home link |
| Quran integration | Student adds an existing ayah to the revision plan from web/native reader |

Validation covers existing ayah, supported rating, nonnegative integer version, student eligibility and ownership. API writes are throttled; web mutations retain CSRF protection. Existing endpoint URLs were preserved.

## Quality and security verification

**Final verification:** 208/208 backend tests PASS, 883 assertions (9.8 seconds); mobile TypeScript PASS; 7/7 offline-cache tests PASS; production Vite build PASS; Pint PASS. Targeted coverage includes:

- missing authentication / incorrect role;
- cross-student card access and cross-group mentor queue/detail/AI/audio/write access;
- invalid ratings/ayah IDs, adaptive scheduling and replay/version rejection;
- persisted review history and unchanged canonical Arabic;
- separate AI/mentor errors and actual web submission flow;
- zero/missing/negative/out-of-range ASR confidence;
- course/group management denial for students;
- existing API, family isolation, uploads, notifications and education regressions.

Existing assignment fixtures were corrected to assign the authorized mentor to their test group. Assertions were not weakened; the previous unconditional `expect(true)` authorization assertion now actually asserts forbidden access.

Chromium: 90 route/viewport checks at 360, 390, 768, 1024, 1440, 1920 px: no final route errors, document horizontal overflow, unlabeled inspected form controls or page exceptions. Student learning page and Expo learning screen at 390px loaded without overflow or page errors. Russian profile switching passed. Real waveform/microphone content was not fabricated for browser tests.

Performance audit is limited to bounded query/result shapes (due list max 20, upcoming max 7, assignments max 5, error groups max 20; eager loading prevents per-card ayah/surah queries) and built asset sizes. Concurrent-user load testing, PostgreSQL EXPLAIN analysis, large-corpus alignment memory/latency and production p95 measurements are NOT TESTED.

## Remaining work / external boundaries

| Requested area | Status | Next requirement |
| --- | --- | --- |
| Live speech provider / alignment accuracy | BLOCKED / NOT TESTED | Real configured ASR, consented labelled recordings and accuracy/timing evaluation. Current tests use provider doubles. Confidence heuristic is not a calibrated probability. |
| Realtime word following | NOT IMPLEMENTED | Streaming audio/provider protocol and verified alignment timestamps. |
| Automatic tajweed grading | BLOCKED | Existing unavailable provider remains explicit; a validated model must be connected before automatic grades. |
| Complete approved tajweed laboratory | NOT IMPLEMENTED | Qualified review and licensed rule examples/audio; current teacher-guided lessons are not a full lab. |
| Material-grounded AI Q&A | NOT IMPLEMENTED | Approved source retrieval, source citations, teacher escalation and fatwa/unsupported-answer boundaries. |
| Uzbek/Russian Quran translations | BLOCKED | Live translation counts are both zero; obtain verified licensed text and provenance before import. |
| Licensed reciter audio / repeat playback | BLOCKED | No licensed reciter row is configured; supply approved source/license and provider mapping. |
| Synced Quran notes/bookmarks/reading position | PARTIAL | Device bookmarks exist; server sync, notes and cross-device reading position remain absent. |
| Parent portal | PARTIAL | Existing linked-child grades/attendance/progress remain; expanded weekly reports and schedule/homework consolidation remain. |
| Goals, achievements, streaks, certificates | NOT IMPLEMENTED | Persist explicit noncompetitive rules; no mastery awards from self-rating. |
| Live video lessons | BLOCKED | No real video provider/account is connected; no fake join buttons added. |
| Advanced center analytics | PARTIAL | Real counts/charts/group capacity exist; full mentor/course/student/date-filtered effectiveness reporting remains. |
| Production infrastructure | NOT TESTED | PostgreSQL, Redis, S3/MinIO, queue supervision, private-object policy and operational restoration must be verified. |
| Audio retention, guardian consent, deletion | NOT IMPLEMENTED | Agree center policy/retention with appropriate specialists; implement consent and audited deletion workflows. No invented legal policy is asserted. |
| Backup/restore operation | NOT TESTED | Commands are present in concurrent workspace changes; no production restore was executed in this delivery. |
| Native microphone/pause-resume/upload progress/push | PARTIAL / NOT TESTED | Existing recorder handles recording/upload/errors; physical devices, pause/resume UX, percent upload progress and push credentials still require work. |
| Mobile lint | NOT TESTED | ESLint is not configured. Automatic installation was stopped and manifest additions reverted to respect dependency approval requirements. |

## Production prerequisites

Use the real PostgreSQL connection and tested additive migration rollout; configure Redis/worker supervision and private S3/MinIO storage; verify backups by restoring into an isolated environment; configure actual speech credentials only on the server; use TLS, secure session/token configuration and monitoring. Do not deploy demo users/passwords. Complete consent/retention/deletion policy and native device/security/performance QA before declaring production readiness.

## Next phase

Phase 3: evaluate the real ASR/word-alignment pipeline with consented recordings, improve timestamp/confidence coverage and mentor confirmation of individual AI findings. In parallel, obtain licensed translation/reciter/tajweed resources. The remainder of phases 3–8 stays explicitly incomplete; this report does not claim the platform outperforms Tarteel or Quran.com without evidence.
