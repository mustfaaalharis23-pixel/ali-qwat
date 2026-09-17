#!/usr/bin/env bash
set -euo pipefail

# The key belongs in the service's environment. This only keeps a brand new
# service from failing on its very first boot — a key that changes with every
# deploy signs everyone out of the panel.
if [ -z "${APP_KEY:-}" ]; then
  APP_KEY="$(php artisan key:generate --show)"
  export APP_KEY
  echo "APP_KEY is not set: using a temporary one for this boot."
fi

php artisan migrate --force

# Filling the test database is a one-off: set RUN_SEED=true, deploy, then put
# it back to false so the next deploy does not seed on top of the data.
if [ "${RUN_SEED:-false}" = "true" ]; then
  php artisan db:seed --force
fi

php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host 0.0.0.0 --port "${PORT:-10000}"
