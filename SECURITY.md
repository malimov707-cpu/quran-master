# Security

## Controls

- Sanctum authentication and server-side RBAC.
- Password hashing, login throttling, CSRF protection and secure session cookies.
- Strict upload validation and object-storage isolation.
- Password/API secrets must only be supplied through environment or secret manager.
- SQL access uses Eloquent/query builder parameterization.
- Authorization must be enforced on every state-changing endpoint.
- Audit records must cover permission-sensitive actions.

## Child data

Audio, mentor feedback and account data are treated as restricted child data. Collection requires guardian consent; production retention, export and deletion policies must be approved before launch.

## Production release gate

- Dependencies scanned for vulnerabilities.
- Production secrets rotated and not included in images.
- HTTPS enforced.
- Backups encrypted and restore tested.
- Audit logging enabled.
- Real device security and privacy review completed.
