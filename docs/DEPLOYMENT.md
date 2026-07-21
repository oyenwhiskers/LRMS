# LRMS Production Deployment and Recovery

## Preconditions

- PHP 8.2 with PDO MySQL, DOM, GD, Intl, Mbstring, XML and ZIP
- MySQL 8 database and restricted application user
- HTTPS certificate and web root set to `public/`
- Node.js available during build, or prebuilt `public/build` artifacts
- Supervised queue worker and one-minute scheduler
- Encrypted backup destination separate from the server

## First deployment

1. Back up any existing database and application files.
2. Deploy to a timestamped release directory.
3. Copy the production `.env`; set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, MySQL, mail/log and admin bootstrap variables.
4. Run:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate --show
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
```

Set `APP_KEY` from a securely generated value; do not replace an existing production key.

5. Grant the web process write access only to `storage/` and `bootstrap/cache/`.
6. Start/reload PHP-FPM, the queue worker and scheduler.
7. Point the web-server release symlink to the new release.
8. Verify `/up`, login, authorization, QR camera permissions, label printing and one reversible test transaction.
9. Remove the bootstrap admin password variables after the account exists and reseed only when explicitly required.

## Routine release

1. Announce a short transaction freeze.
2. Take a verified database backup.
3. Build a new release without modifying the active release.
4. Run tests and dependency audits.
5. Enable maintenance mode, migrate, optimize and switch the release symlink.
6. Reload workers and run smoke tests.
7. Disable maintenance mode and end the freeze.

## Backup and restore

- Back up MySQL at least daily with transaction-consistent settings.
- Back up `storage/app` and production configuration separately.
- Encrypt in transit and at rest; restrict backup access.
- Retain daily backups for 30 days and monthly backups according to firm policy.
- Monitor backup completion and available disk space.
- Test a full restore to an isolated environment quarterly.

Restore procedure:

1. Isolate the failed environment and preserve logs.
2. Provision a clean compatible release.
3. Restore the selected database and `storage/app` snapshot.
4. Restore `.env` and verify `APP_KEY` is unchanged.
5. Run `php artisan optimize`, restart workers and validate counts.
6. Test login, search, one QR lookup and recent movement history before reopening.

## Rollback

- If migrations are backward-compatible, switch the release symlink back and reload workers.
- If data changed incompatibly, stop all writes, restore the pre-release database backup, then switch back.
- Never run destructive migration rollback commands against production without reviewing their data impact.
- Record the incident, affected transaction window and reconciliation owner.

## Monitoring

Monitor HTTPS availability, `/up`, HTTP 5xx rates, Laravel logs, failed jobs, MySQL health, disk usage, certificate expiry and backup completion. Treat movement transaction errors and repeated authorization failures as priority alerts.
