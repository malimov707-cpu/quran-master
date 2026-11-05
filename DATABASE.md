# Database

Laravel migrations are the schema source of truth. Production targets PostgreSQL 17; SQLite remains available only for local development and tests.

## Core domains

- Identity: users, roles, permissions, user roles, role permissions, sessions and personal access tokens
- Education: courses, course modules, groups, group membership, mentors, lessons and schedules
- Attendance: schedule-specific attendance records and timestamps
- Qur'an: surahs, ayahs, reciters, recitations and tajweed rules
- Homework: assignments, submissions, files, AI reviews and mentor reviews
- Communication: in-app notifications and notification preferences
- Progress and reporting: stored student progress derived from durable records

## Rules

- Foreign keys, unique constraints and indexes belong in migrations.
- Monetary and child-safety requirements must be reviewed with the security model before production.
- UTC is canonical for database timestamps.
- Display timezone is configured separately through `APP_DISPLAY_TIMEZONE`.
