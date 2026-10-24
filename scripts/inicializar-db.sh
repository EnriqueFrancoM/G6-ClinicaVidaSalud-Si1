#!/bin/sh
# Inicializa la base solo en el primer despliegue de Render.
set -eu

: "${DATABASE_URL:?Falta DATABASE_URL}"
: "${SEED_INITIAL_PASSWORD:?Falta SEED_INITIAL_PASSWORD}"

if [ "$(printf %s "$SEED_INITIAL_PASSWORD" | wc -c | tr -d ' ')" -lt 8 ]; then
    echo "SEED_INITIAL_PASSWORD debe tener al menos 8 caracteres." >&2
    exit 1
fi

if psql "$DATABASE_URL" -tAc "SELECT to_regclass('public.rol') IS NOT NULL" | grep -qx 't'; then
    echo "La base ya contiene el esquema; no se vuelve a inicializar."
    exit 0
fi

HASH_INICIAL="$(SEED_INITIAL_PASSWORD="$SEED_INITIAL_PASSWORD" php -r 'echo password_hash(getenv("SEED_INITIAL_PASSWORD"), PASSWORD_DEFAULT);')"

psql "$DATABASE_URL" --set=ON_ERROR_STOP=1 --single-transaction -f database/01_schema.sql
psql "$DATABASE_URL" --set=ON_ERROR_STOP=1 --single-transaction \
    --set="seed_password_hash=$HASH_INICIAL" \
    -f database/02_seed.sql

echo "Esquema y datos iniciales creados correctamente."
