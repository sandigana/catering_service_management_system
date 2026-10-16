# docker/start.sh
# Container entrypoint on Render: fix the app key, prepare storage, migrate, seed the admin,
# warm caches, then start Apache. Run as `sh docker/start.sh` (see Dockerfile CMD).
set -e
cd /var/www/html

# Render's generated APP_KEY is plain base64 (44 chars); Laravel expects the "base64:" prefix.
if [ -n "$APP_KEY" ] && [ "${APP_KEY#base64:}" = "$APP_KEY" ] && [ "${#APP_KEY}" -eq 44 ]; then
  export APP_KEY="base64:$APP_KEY"
fi
if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY is not set." >&2
  exit 1
fi

# Links in e-mails and image URLs: default to the URL Render assigned (override with APP_URL for a custom domain).
export APP_URL="${APP_URL:-$RENDER_EXTERNAL_URL}"

# Directories Laravel writes to (a mounted disk can hide the ones created at build time).
mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
         storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Public image uploads are served through /storage.
php artisan storage:link --force || true

# Everything below runs as www-data so cache files stay writable by Apache.
runuser -u www-data -- php artisan migrate --force
# Creates the first admin from ADMIN_EMAIL / ADMIN_PASSWORD; safe to repeat (never resets an existing password).
runuser -u www-data -- php artisan db:seed --force
runuser -u www-data -- php artisan config:cache
runuser -u www-data -- php artisan view:cache
# route:cache is skipped on purpose: routes/web.php contains a closure route.

exec apache2-foreground
