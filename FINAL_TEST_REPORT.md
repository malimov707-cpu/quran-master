# Final Test Report

## Backend

- Command: `php artisan test --compact`
- Result: 232 passed, 978 assertions.

## Database

- Command: `php artisan migrate:fresh --env=testing --no-interaction`
- Result: passed.

## Web

- Command: `npm run build`
- Result: passed.
- Existing warning: an optional font optimization package is not installed.
- Existing asset resolution notices: referenced public JPG paths are retained as runtime public assets.

## Mobile

- Command: `npm run typecheck`
- Result: passed.
- Command: `npx expo export --platform all`
- Result: web, Android, and iOS bundles exported successfully.

## Added coverage

- API attendance rejects users outside the schedule group.
- API attendance rejects duplicate student rows.
- Expo tokens are routed to Expo Push.
- Push device registration/removal remains user-owned.
- Nested Expo stale-token handling executes as an independent test.
- Mobile exports pass for Web, Android, and iOS.
