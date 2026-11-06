# Security Audit

## Findings

| ID | Severity | Area | Result |
|---|---|---|---|
| SEC-001 | MEDIUM | Attendance API ownership | Fixed: schedule group membership and duplicate student validation added |
| SEC-002 | MEDIUM | Push token ownership | Existing API validates and removes only authenticated user's devices |

## Controls

- Sanctum authentication protects API mutations.
- Permission checks exist for role-governed actions.
- API audio routes apply submission access checks.
- Private storage signed URLs are used for private submission files where implemented.
- Push tokens are never returned to other users by a bulk endpoint.
- No provider secret is read by the Expo client.

## External checks required

- Independent penetration test.
- Real device/private file URL expiration test.
- FCM token deletion and invalid-token behavior under live provider credentials.
- Production TLS, rate limit, headers, and secret scanning review.
