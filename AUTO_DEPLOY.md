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

Deployment output is recorded in `storage/logs/deploy.log` on the server.
