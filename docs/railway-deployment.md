# Deploying LPG on Railway

## Build and start

- Connect this GitHub repository to a Railway service.
- Use Railway's PHP/Composer build detection. The project requires PHP 8.2+ and the intl, mbstring, mysqli, and zip extensions.
- Set the service start command to `composer start` if Railway does not automatically use the Composer start script.
- The start script serves the CodeIgniter `public/` directory and listens on Railway's injected `PORT`.

## Railway Variables

Set these in the Railway service's Variables panel. Do not put credentials in source control.

| Variable | Required | Purpose |
| --- | --- | --- |
| `CI_ENVIRONMENT` | Yes | Set to `production` |
| `app.baseURL` | Yes | Full public URL ending in `/`, e.g. `https://your-service.up.railway.app/` |
| `LPG_DB_HOST` | Yes | MySQL host from Railway database connection details |
| `LPG_DB_NAME` | Yes | Target database name |
| `LPG_DB_USER` | Yes | Database username |
| `LPG_DB_PASS` | Yes | Database password |
| `LPG_DB_PORT` | Yes | MySQL port |
| `LPG_DB_DRIVER` | No | Defaults to `MySQLi` |
| `FRESH_DATABASE_SETUP` | No | Keep `false` normally; temporarily set `true` only for first-time setup |
| `app.companyName` | No | Company name shown in the UI |
| `encryption.key` | Recommended | A long, unique CodeIgniter encryption key |
| `logger.threshold` | No | Set an appropriate production log threshold |

Use the exact host, database, username, password, and port provided by the MySQL service. If Railway's MySQL service exposes different variable names, map those values to the LPG_DB_* variables above.

## First-time database setup

1. Provision a MySQL database reachable from the application service.
2. Set all required variables above and verify the app can reach MySQL.
3. Temporarily set `FRESH_DATABASE_SETUP=true`.
4. Open the public application URL and follow Installation Setup. Existing populated databases require administrator verification and a verified backup before replacement.
5. Verify that setup completes and that an administrator can log in.
6. Set `FRESH_DATABASE_SETUP=false` immediately after installation.

The application database account needs permission to create the database and tables during a fresh install. Existing-database replacement also requires DROP DATABASE permission. Grant only the permissions required for your deployment.

## Important notes

- Composer metadata cannot create Railway variables or inject secrets into a running service. Runtime variables must be entered in Railway's Variables panel; the Composer script only provides the web start command.
- Do not set `CI_ENVIRONMENT=development` in production.
- Ensure the `writable/` directory is writable by the application process. Local filesystem backups may not be durable across redeployments, so configure an external backup destination and test restores before relying on database replacement.
- Keep `FRESH_DATABASE_SETUP=false` during normal operation.