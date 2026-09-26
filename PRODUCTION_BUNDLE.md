# Tisilo production copy package

The versioned production archive in `dist/` is intended for a cPanel server
whose document root is `public_html`.

It contains:

- the complete Laravel application source;
- Composer's tested production dependencies (`vendor/`);
- compiled frontend assets;
- the current public media library;
- a safe root front controller and internal rewrite rules, so `/public` is not
  added to website URLs.

It intentionally excludes plaintext credentials from Git history, plus runtime
state such as logs, caches, sessions, queues, test caches, temporary archives,
`node_modules`, and Git metadata. The deployment helpers preserve the exact
production `.env` (including `APP_KEY`, database settings, and API credentials)
as `/home/rirakib/TisiloBackup/.env.production` with mode `0600`, then restore it
automatically when `public_html/.env` is missing.

## Restore

1. Keep the server's existing production `.env` file. For a rebuilt account,
   restore the protected `/home/rirakib/TisiloBackup/.env.production` backup.
2. Extract the archive directly into `public_html`.
3. Import the production database when it is a new server.
4. Ensure `storage/` and `bootstrap/cache/` are writable by PHP.
5. Open the site. The root front controller recreates the standard
   `public/storage` link if an archive transfer did not preserve it.

For normal updates, use `scripts/deploy-production.sh`; it keeps production
credentials, customer data, catalogue changes, shipping rates, and uploaded
media on the server instead of replacing them from a developer machine.
