# Database Backup & Restore Change Log

## 2026-10-10 — Railway-compatible backup handling

### Changes committed directly to main
- Added a portable SQL backup path for deployments where `mysqldump` is not installed.
- Added a matching portable restore path for backups created by the portable exporter when the `mysql` client is unavailable.
- Kept the configured MySQL client path for environments such as local XAMPP, so a configured `mysqldump` / `mysql` pair can still be used.
- Added an authenticated download route for saved backup files.
- Updated the page to warn that Railway service files may be temporary and that backups should be downloaded or emailed to secure storage.
- Retained the pre-restore safety-backup behavior.

### Portable format scope and limits
- The portable exporter includes base-table definitions and row data.
- It does not export stored procedures, functions, triggers, events, or views. If these are added to the application database, configure the MySQL command-line tools for a full dump/restore.
- SQL restore can replace existing tables and data. The safety backup is created first, but a failed restore can still leave the database partially restored; keep an independent downloaded backup before restoring production data.
- Backups saved only inside a Railway service filesystem are not an independent durable backup. Download them or send them to a separately managed secure destination.

### Verification status
- Repository changes were committed to `main`.
- Automated PHP/runtime and live Railway database backup/restore verification have not been confirmed by this change log. Before relying on production restore, create a backup, download it, and test restore against a non-production database first.

## 2026-10-10 — Email and Google Drive delivery

- Confirmed the backup page already supports SMTP email attachments, test email, and emailing selected saved backups using the existing Email Configuration settings.
- Added an optional `Also upload backup to Google Drive` checkbox when creating a backup.
- Added Google Drive OAuth refresh-token configuration through environment variables:
  - `BACKUP_GOOGLE_DRIVE_CLIENT_ID`
  - `BACKUP_GOOGLE_DRIVE_CLIENT_SECRET`
  - `BACKUP_GOOGLE_DRIVE_REFRESH_TOKEN`
  - `BACKUP_GOOGLE_DRIVE_FOLDER_ID` (optional; if omitted, Drive uses the account's default location)
- Uses Google Drive API v3 resumable upload to stream the SQL file from disk rather than loading the whole backup into PHP memory.
- Upload failure is logged and shown to the user while preserving the local backup file. A successful upload is reported separately from local backup creation.
- Required setup: enable Google Drive API in the Google Cloud project, create an OAuth client, obtain a refresh token with Drive file-creation scope, and grant the connected Google account access to the target folder. Set the variables in Railway's service Variables; use equivalent environment values for local XAMPP. Do not commit credentials to the repository.
- Email delivery uses the existing SMTP settings configured on the page. Large SQL attachments may be rejected by email providers; Google Drive is preferable for large backups.

### Verification
- Code and UI changes committed to `main`.
- Railway deployment status was still pending at the last check. No successful live Google OAuth/Drive upload or SMTP attachment delivery has been confirmed yet. Configure the credentials and test in Railway before relying on the feature.
