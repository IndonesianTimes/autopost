# Social Autopost Engine (SAE)

Minimal service for queueing social posts and dispatching them to external platforms.

## Requirements
- PHP 8.2
- MySQL (PDO)
- `.env` configuration loaded via `Config.php`

## Setup
1. `composer install`
2. Copy `.env.example` to `.env` and adjust database credentials.
3. Run migrations:
   ```bash
   mysql -u user -p dbname < migrations/001_init.sql
   mysql -u user -p dbname < migrations/002_backfill_cols.sql
   php migrations/2025_08_17_p2_nice_to_have.php
   ```
4. Ensure media directory exists (default `public/media/cards`) and is writable.

## Endpoints
- `POST /api/autopost/webhook` – enqueue post via signed webhook.
- `POST /api/autopost/ingest` – basic ingest endpoint (Bearer token).
- `GET /api/autopost/queue` – list queued items (Bearer token, `status` param).
- `GET /api/autopost/posts` – list posted items (Bearer token).
- `GET /go.php?id=SHORT` – shortlink redirect with click metrics.
- `GET /health` – service and token healthcheck.

## Worker
Run every 3 hours via cron (worker self-randomises start delay):
```
0 */3 * * * /usr/bin/php /var/www/autopost/worker.php >> /var/www/autopost/storage/logs/worker.log 2>&1
```

## Troubleshooting
- `php bin/doctor` – checks PHP extensions, DB connection, and storage permissions.
- Review logs under `storage/logs`.

