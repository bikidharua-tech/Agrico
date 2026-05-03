#!/usr/bin/env bash
set -euo pipefail

: "${PORT:=10000}"
: "${API_HOST:=127.0.0.1}"
: "${API_PORT:=8001}"

export API_HOST
export API_PORT

cat >/usr/local/etc/php-fpm.d/zz-render-env.conf <<'EOF'
[www]
clear_env = no
EOF

mkdir -p /var/www/html/python_api/.tmp
mkdir -p /var/www/html/public/uploads/diagnosis
mkdir -p /var/www/html/public/uploads/forum
mkdir -p /var/www/html/public/uploads/avatars

export WEB_PORT="$PORT"
envsubst '${WEB_PORT}' < /etc/nginx/templates/agrico.conf.template > /etc/nginx/conf.d/default.conf

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
