# Tisilo production auto-deployment

The cPanel production server checks the GitHub `main` branch once per minute.
When it finds a new commit, it runs `scripts/deploy-production.sh`, which:

1. creates a compressed database backup;
2. enables Laravel maintenance mode;
3. fast-forwards the server clone to the new commit;
4. installs production Composer dependencies;
5. runs pending database migrations;
6. synchronizes public assets and rebuilds Laravel caches;
7. brings the application online;
8. rolls the code back to the previous commit if deployment fails.

The deploy script also verifies its own cPanel cron entry on every run and
repairs it to `* * * * *` when necessary. A non-blocking `flock` lock prevents
overlapping deployments when a previous release takes longer than one minute.

Deployment output is recorded in `storage/logs/deploy.log` on the server.

Automatic deployment verification marker: `production-v1`.

Public asset permission repair marker: `production-v2`.

One-minute self-healing deployment cron marker: `production-v3`.
