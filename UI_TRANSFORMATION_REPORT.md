# UI transformation report

Updated: 2026-11-06. The later competitive-transformation brief extends this work; see `COMPETITIVE_AUDIT.md` and `COMPETITIVE_TRANSFORMATION_REPORT.md` for functional scope and unfinished work.

## Delivered interfaces

- Shared Blade app/auth layouts: emerald sidebar, geometric gold ornament, reusable SVG brand/icons, collapse preference, mobile drawer, keyboard focus handling, skip link, search, actual notification count and account profile, breadcrumbs, locale selector and persistent light/dark palette.
- Design tokens and glass surfaces cover active course, lesson, group, schedule/attendance, assignment, homework/result, parent, notification and Quran routes. Existing forms remain connected to backend actions.
- Dashboard: actual role-scoped values, 7/30-day attendance, daily registrations when authorized, recent personal notifications, newly registered users when authorized, mentor/group capacity and actual route shortcuts. Empty days remain empty; no synthetic attendance or growth is drawn.
- Administration: users/search/role filter/pagination, roles/permissions, audit log, provider configuration state and safe system metadata. These are read-only data screens. Payments show an explicit unavailable state; payment processing and a settings editor were not implemented.
- Mentor studio: private audio, real PCM-derived browser waveform on request, playback speed, ±5 seconds, AI timestamp seeking, canonical ayah comparison, distinct AI review details and mentor score/feedback form. Resubmission does not require an approval score. Actual-audio waveform rendering still requires a manual real recording check.
- Quran reader: unchanged RTL Arabic, locally served Amiri including Arabic subset, font-size controls, per-account browser bookmarks, numbered/name search, canonical text comparison. No invented tajweed colors, translation or reciter audio.
- New learning page: real due/upcoming revision cards, next assignments and separately identified mentor errors / AI hypotheses.
- Native Expo screens use shared light/dark colors and readable default text. Updated: login, home, Quran catalog/reader/offline library, lessons/detail, assignments/record/result, mentor queue/review, parent children, progress, notifications, profile and learning plan. Profile includes theme, language and logout confirmation. Audio playback includes real timing, accessible position feedback and seek controls. Native microphone/device behavior was not certified in this session.

## Localization

Uzbek Latin, Uzbek Cyrillic and Russian catalogs are shared in content between web and mobile, with an English-ready source-key fallback. Visible static controls, navigation and the new learning page are translated. Arabic is a separate RTL region; user names and canonical text are not transliterated. Full localization of stored notification bodies, server validation messages, dynamic summaries and every legacy date/unit remains PARTIAL.

## Licensed photography

Active assets:

- `public/images/islamic-civilization-center.jpg`: 1920px web image.
- `public/images/islamic-civilization-center-mobile.jpg`: optimized mobile background.
- `mobile/assets/islamic-civilization-center.jpg`: approximately 98 KB native image, cropped by its cover container.

[Original photograph](https://commons.wikimedia.org/wiki/File:Center_of_Islamic_Civilization_of_Uzbekistan_Tashkent_2026_001.jpg): Зинҳор Насрӣ, 13 June 2026, **CC BY-SA 4.0**. Changes: downscaling/recompression and display cropping/gradient overlay. The photograph/its resized copies retain [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/). Attribution is visible in web app/auth footers and mobile profile. This credit does not imply endorsement.

The earlier built-in imagegen courtyard was an illustration and was replaced and removed to meet the later requirement for a real building photograph. Its generation prompt was: “Wide panoramic serene Central Asian mosque courtyard, elegant teal emerald domes and slim minarets, ivory stone arches, reflecting pool, olive greenery at edges, warm golden sunrise sky. Architectural photography, natural light, realistic detail, restrained emerald ivory gold palette. Background only, no interface, no writing, no logos, no Quran text, no watermark.” The active assets above are licensed photography, not generated images.

## Verification

- Chromium: 90 checks across 15 admin-accessible routes × 360/390/768/1024/1440/1920px. Final run: all HTTP 200; no document horizontal overflow; no unlabeled input/select/textarea findings in these routes; no browser page errors. The first run found a 768px topbar overflow and old unlabeled course/group fields; both were fixed.
- Student web learning page and Expo web learning screen at 390px: loaded, no overflow, no page errors. Mobile Russian profile controls switched correctly.
- Mobile TypeScript: PASS. Offline Quran cache: 7/7 tests PASS.
- Vite production build: PASS. CSS approximately 87 KB / 17.6 KB gzip; app JS approximately 5.4 KB / 2.1 KB gzip. This is asset sizing, not a production load benchmark.
- Backend suite and security results are recorded in the competitive report.
- Expo lint: NOT TESTED to completion; no ESLint configuration was installed. CLI automatic dependency installation was stopped and its manifest additions reverted.
- Physical Android/iOS, screen-reader audit, real microphone/waveform verification, screenshot review of every populated detail state, push notifications and production performance: NOT TESTED.

Remaining acceptance work: complete dynamic localization, native-device QA, licensed reciter/translation content, comprehensive populated-state visual coverage and provider integrations. The entire premium/competitive specification is not declared complete.
