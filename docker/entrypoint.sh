#!/bin/sh
set -eu

PUERTO="${PORT:-10000}"
case "$PUERTO" in
    ''|*[!0-9]*)
        echo "PORT debe ser numérico." >&2
        exit 1
        ;;
esac

sed -ri "s/^Listen [0-9]+$/Listen ${PUERTO}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PUERTO}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
