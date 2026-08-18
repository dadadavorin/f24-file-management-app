#!/bin/sh
set -eu

# Railway's pre-deploy command (migrations) runs this same image with an
# overridden command; when one is given, run it instead of serving.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

: "${PORT:=8080}"

sed "s/__PORT__/${PORT}/g" /etc/nginx/templates/default.conf.template \
    > /etc/nginx/http.d/default.conf

# php-fpm runs backgrounded so nginx (the process Docker sends signals to)
# can own the container's foreground and exit code.
php-fpm --nodaemonize &

exec nginx -g 'daemon off;'
