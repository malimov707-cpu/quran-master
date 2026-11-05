# API

The mobile and web clients use the common Laravel REST API under `/api/v1`.

## Authentication

- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- Sanctum Bearer authentication for protected routes

## Educational resources

- Courses and groups
- Qur'an surahs and search
- Assignments and submissions
- AI review retrieval
- Student progress
- Notifications
- Parent-child relationships

## Contract rules

- Validation errors use Laravel JSON validation responses.
- Protected endpoints require both authentication and backend authorization.
- Pagination and filters are implemented server-side.
- OpenAPI generation and typed client generation are required before public release.
