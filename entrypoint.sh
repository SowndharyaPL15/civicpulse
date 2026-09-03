#!/bin/bash
set -e

echo "========================================="
echo "   Starting CivicPulse Production App   "
echo "========================================="

# 1. Dynamic Port Configuration (for Render, Railway, Fly.io, Cloud Run)
PORT="${PORT:-80}"
echo "[CivicPulse Entrypoint] Configuring Apache to listen on port ${PORT}..."
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
sed -i "s/<VirtualHost \*:8080>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf 2>/dev/null || true

# 2. Ensure Uploads and Permissions
mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads
chmod -R 775 /var/www/html/uploads

# 3. Wait for Database Connection & Run Initializer
echo "[CivicPulse Entrypoint] Running database auto-initializer..."
MAX_TRIES=30
COUNT=0
UNTIL_SUCCESS=false

while [ $COUNT -lt $MAX_TRIES ]; do
    if php /var/www/html/init_db.php; then
        UNTIL_SUCCESS=true
        break
    else
        COUNT=$((COUNT+1))
        echo "[CivicPulse Entrypoint] Database not ready yet (attempt $COUNT/$MAX_TRIES). Retrying in 2 seconds..."
        sleep 2
    fi
done

if [ "$UNTIL_SUCCESS" = false ]; then
    echo "[CivicPulse Entrypoint] Warning: Could not complete database initialization automatically."
    echo "[CivicPulse Entrypoint] Please verify your database connection credentials."
fi

# 4. Start Apache in foreground
echo "[CivicPulse Entrypoint] Starting Apache HTTP Server on port ${PORT}..."
exec apache2-foreground
