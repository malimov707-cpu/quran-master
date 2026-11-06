# Pre-Deployment Readiness

## PASS

- Application implementation reviewed.
- Backend, migration, and mobile automated checks pass.
- Required production secrets are enforced by Compose.
- Deployment and rollback scripts have valid syntax.
- Docker application image tags are supported.

## BLOCKED

- Staging and production servers.
- PostgreSQL, Redis, S3, DNS, and TLS credentials.
- Firebase, Expo, and AI provider credentials.
- Android/iOS signing accounts and real devices.
- Licensed Quran audio and alignment datasets.

## Next Actions

1. Supply staging infrastructure and deployment credentials.
2. Configure HTTPS before exposing staging publicly.
3. Perform staging backup and destructive restore rehearsal.
4. Supply real push credentials and test devices.
5. Validate AI providers with approved sample audio.
6. Validate licensed audio and alignment coverage.
7. Create signed Android/iOS artifacts through owner-controlled accounts.
