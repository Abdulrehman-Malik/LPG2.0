# First-Time Database Installation

## Configure the environment

Before opening the installer, configure the environment for the target customer/deployment. Copy the repository's `env` example to `.env` for manual hosting, or enter equivalent values in your hosting provider's environment/Variables panel. Never commit real passwords or production secrets.

| Variable | Purpose |
| --- | --- |
| `CI_ENVIRONMENT` | Use `production` for a public deployment; use `development` only locally. |
| `app.baseURL` | Full application URL, including the trailing slash. |
| `database.default.hostname` / `LPG_DB_HOST` | MySQL hostname. The explicit `LPG_DB_HOST` value takes precedence when provided. |
| `database.default.database` / `LPG_DB_NAME` | Target database name. `LPG_DB_NAME` takes precedence when provided. |
| `database.default.username` / `LPG_DB_USER` | MySQL username. |
| `database.default.password` / `LPG_DB_PASS` | MySQL password; store secrets in the hosting provider, not source control. |
| `database.default.port` / `LPG_DB_PORT` | MySQL port, commonly 3306. |
| `database.default.DBDriver` / `LPG_DB_DRIVER` | Use `MySQLi` for MySQL; the explicit `LPG_DB_DRIVER` value takes precedence when provided. |
| `encryption.key` | Recommended unique secret for production; keep stable between deployments and private. |
| `app.companyName` | Optional company name shown on the login page and browser title. |
| `FRESH_DATABASE_SETUP` | Installer feature flag described below. |

## What the installation flag does

- `FRESH_DATABASE_SETUP = false` is the safe default. It leaves the installation workflow disabled during normal application use.
- Temporarily set `FRESH_DATABASE_SETUP = true` only while setting up a new deployment. If installation has not completed, opening login redirects to Installation Setup.
- The installer creates the configured database if it does not exist, applies `database/schema.sql`, runs pending numbered migrations in order, and records completion only after success.
- The destructive transactional-data refresh migration (`011_20261003_refresh_transactional_data.sql`) is excluded from automatic installation.
- After setup and administrator login have been verified, set `FRESH_DATABASE_SETUP = false` immediately.

## Normal operation

Keep this setting disabled in the deployment .env file:

    FRESH_DATABASE_SETUP = false

The installer does not run while this flag is false. Regular login and the existing Transactional Data Refresh feature remain separate.

## Deploying a new customer

1. Configure the target database host, username, password, port, and database name in .env (database.default.* or the supported LPG_DB_* variables).
2. Ensure the MySQL account has permission to create the configured database and create/alter tables. For replacement, it also needs permission to drop that database.
3. Set FRESH_DATABASE_SETUP = true for the initial setup window.
4. Open the application login URL. If setup has not completed, the login page redirects to Installation Setup.
5. Review the target database name and submit INSTALL DATABASE.
6. The installer creates the database if it does not exist, executes database/schema.sql, runs pending numbered SQL migrations in sequence, and records completion only after the migrations succeed.
7. Confirm that login works, then set FRESH_DATABASE_SETUP = false.

## Replacing an existing database

**Replacement permanently deletes all tables and records in the configured database.** The installer requires all of the following before replacement:

- The existing database has an active user with the ADMIN role.
- The submitted password matches that administrator's stored password hash.
- The operator types REPLACE DATABASE and INSTALL DATABASE and confirms the browser warning.
- A verified SQL backup is created successfully before the database is dropped.

If the existing database has tables but no verifiable active administrator, replacement is blocked. If backup creation fails, the existing database is not dropped.

## Migration behavior

- Schema creation runs before numbered migrations.
- Migrations are processed in ascending sequence order.
- Migration history and checksums use the existing migration runner.
- A failed migration blocks completion and login; fix the cause and retry.
- Migration 011_20261003_refresh_transactional_data.sql remains excluded from automatic execution because it is a separate destructive refresh workflow.

## Important operational notes

- The schema script is intended for an empty database. It no longer creates/selects a hard-coded database or drops tables itself; the installer controls database creation and replacement.
- Do not leave FRESH_DATABASE_SETUP = true enabled after deployment. The completion marker prevents automatic re-entry on each login, but the installation route remains available while the flag is enabled.
- Keep backups outside the database server when possible and periodically test restore procedures.
