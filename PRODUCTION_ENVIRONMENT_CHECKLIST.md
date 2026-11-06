# Production Environment Checklist

- Unique 32-byte Laravel APP_KEY.
- `APP_DEBUG=false`, production-safe logs, HTTPS APP_URL.
- PostgreSQL host restricted to application network.
- Redis authentication and private network.
- Private S3 bucket; public access disabled.
- Separate databases for development and production.
- Queue and scheduler services running.
- FCM/Expo credentials configured only in secret management.
- AI provider credentials configured only in backend environment.
- Mail provider configured instead of `log`.
- Backup disk credentials and retention policy configured.
- Monitoring, uptime checks, error tracking, and alert recipients configured.
- Mobile release API URL and EAS project ID configured.
- Android package/signing and iOS bundle/signing configured.
- Browser and native deep links tested.
