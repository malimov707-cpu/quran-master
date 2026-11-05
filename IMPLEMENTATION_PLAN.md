# QUR'ON AI USTOZ — IMPLEMENTATION PLAN

## Current implementation status (2026-11-04)

- Repository audit completed; existing Laravel modular monolith is preserved.
- Foundation, Docker Compose runtime, API authentication, RBAC, courses, groups, mentor assignment, lessons, schedules and attendance are implemented.
- Web and mobile clients use the shared `/api/v1` backend contract.
- Quran source import, lesson, assignment, submission, mentor review, progress, notification and AI integration modules are implemented with integration-ready provider boundaries.
- Remaining release gates are live infrastructure credentials, provider credentials, real-device mobile/audio acceptance, external HTTPS and validated backup/restore.
- Docker app entrypoint performs migrations and serves the API; a dedicated Redis queue worker runs background AI jobs.
- Known constraint: the prompt requested Java/Spring Boot, while production work continues on the repository's existing Laravel 13/PHP 8.4 stack. Migration to Java is a platform rewrite, not a safe incremental change to existing working code.

## Phase 0 — Repository Audit (2026-11-04)

### Topilgan holat
- Yangi **Laravel 13** skeleton (PHP 8.4), `sqlite` bazada, Vite + Tailwind 4 frontend, Pest 5 test framework.
- Kod mavjud emas: faqat default `User`, 3 default migration, bo'sh `routes/web.php`.
- Muhit: Java 21 bor, **Maven yo'q**, Docker sozlanmagan, PostgreSQL/Redis yo'q.

### Texnik qarorlar (arxitektura)
| Qaror | Asos |
|---|---|
| Backend: **Laravel (modul monolith)** Spring Boot o'rniga | Muhitda Maven yo'q — Java build imkonsiz; Laravel toolchain to'liq ishlaydi. Modul qatlamlari (Controller/Service/Repository/DTO) va ER sxema Spring dizayniga ekvivalent, Java'ga ko'chirish uchun hujjatlashtiriladi. |
| API: `/api/v1/*`, Sanctum token auth (Bearer) — Web, Android, iOS uchun yagona API | Bir backend — uch platforma. |
| Web: Laravel Blade + Vite + Tailwind 4 (Livewire o'rniga oddiy SPA-less dashboard) | Skeleton allaqachon Tailwind/Vite bilan; alohida React repo o'rniga bitta deployable. |
| DB: Flyway o'rniga **Laravel migrations** | Framework-native, versiyalangan, tartibli. |
| AI: Provider interfeyslari (SpeechRecognition/QuranAlignment/TajweedAnalysis/Explanation), queue jobs, retry/timeout | Provayderga bog'lanmaslik, integration-ready. |
| Qur'on matni: Tanzil.net manba importi (checksum bilan), tahrirlanmaydi | Soxta matn taqiqlangan. |

### Muammolar / risklar
1. Java stack bu mashinada build bo'lmaydi — Laravel variant tanlandi (yuqorida).
2. PostgreSQL o'rnatilmagan — dastlab sqlite, production uchun `DB_CONNECTION=pgsql` tayyor konfiguratsiya yoziladi.
3. AI kalitlari `.env` da faqat backendda saqlanadi.

---

## Phase 1 — FOUNDATION (joriy bosqich)
- RBAC migratsiyalari: roles, permissions, user_roles, role_permissions, refresh_tokens (Sanctum), audit_logs, center_members, system_settings, notifications.
- 6 rol: SUPER_ADMIN, CENTER_ADMIN, HEAD_TEACHER, MENTOR, STUDENT, PARENT.
- Auth API: register (admin yaratadi), login, logout, me, refresh. Backendda permission tekshiruvi middleware orqali.
- Web skaffold: login sahifasi, rol bo'yicha dashboard, sidebar navigatsiya.
- Testlar: auth, role/permission feature testlari.

## Phase 2 — Education Management
courses, course_modules, groups, group_students, mentor_assignments, lessons, lesson_materials, schedules, attendance + CRUD API + web UI.

## Phase 3 — Quran Learning
quran_surahs, quran_ayahs, quran_juz, quran_pages, quran_reciters, tajweed_rules; Tanzil import command (checksum); reader UI (arab matn, tarjima, tajvid ranglari, RTL).

## Phase 4 — Homework & Review
assignments, submissions, submission_files; audio upload (S3-ready disk); Mentor Review Studio; mentor_reviews, mentor_review_segments; qayta topshirish.

## Phase 5 — AI Engine
`ai_reviews`, `ai_review_segments`, `background_jobs`, provider interfaces, queue workers, confidence/uncertainty → avtomatik mentor review'ga yuborish.

## Phase 6 — Analytics & Notifications
student_progress, reports, notification system (in-app + FCM tayyor), parent dashboard.

## Phase 7 — Mobile
Expo (React Native + TS + Expo Router), SecureStore, expo-av recorder, push, offline sync, deep linking.

## Phase 8 — Production
Docker compose, CI/CD, backup/restore, security audit, monitoring, hujjatlar to'liq to'plami (ARCHITECTURE.md, DATABASE.md, API.md va h.k.).
