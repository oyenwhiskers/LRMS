# Legal Records Management System (LRMS)

LRMS manages the identity, storage location and movement of physical legal files. It does not store legal documents.

Operational handoff:

- [User and clerk guide](docs/USER-GUIDE.md)
- [User acceptance test](docs/UAT.md)
- [Deployment, backup and rollback](docs/DEPLOYMENT.md)

## Technology

- PHP 8.2 and Laravel 12
- MySQL 8 in production; SQLite for automated tests
- Blade, Livewire, Tailwind CSS 4 and Vite 7
- QR scanning through device cameras or keyboard/USB scanners
- SVG QR identity labels, PDF output and Excel import/export

## Local setup

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Configure `LRMS_ADMIN_NAME`, `LRMS_ADMIN_EMAIL` and `LRMS_ADMIN_PASSWORD` before running the production seeder. Never commit `.env`.

## Test and quality commands

```bash
vendor/bin/pint --test
php artisan test
npm run build
composer audit
npm audit
```

The local Windows PHP installation must enable `pdo_sqlite` and `sqlite3` for tests. Production requires PDO MySQL, DOM, GD, Intl, Mbstring, XML and ZIP.

## Production deployment

1. Use HTTPS with the web root pointed to `public/`.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, secure MySQL credentials and a generated `APP_KEY`.
3. Use database-backed sessions/cache and configure a supervised queue worker.
4. Run `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan db:seed --force`, and `php artisan optimize`.
5. Run `php artisan schedule:run` every minute through cron/Task Scheduler.
6. Back up the MySQL database and uploaded import/audit files daily. Encrypt backups, retain at least 30 days, and test restoration quarterly.
7. Monitor `/up`, application logs, queue failures, disk usage and backup completion.

## Operating controls

- QR payloads contain opaque identifiers only.
- Admin accounts bypass position permissions; approved user accounts inherit action permissions from one active job position.
- Borrow, return, missing and found events are immutable movement records.
- Borrow/return changes use database transactions and file-row locks.
- PWA caching is limited to static build assets; transactions always require the server.

## Release checklist

- Run all automated tests and dependency audits.
- Test real employee/file QR labels with the target printer and scanners.
- Complete clerk, lawyer and administrator UAT.
- Perform a trial Excel import and verify counts.
- Confirm backups and a restore rehearsal.
- Document cutover and rollback owners before production migration.
