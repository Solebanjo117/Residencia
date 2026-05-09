#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

log() {
    printf '\n==> %s\n' "$1"
}

fail() {
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "No se encontro el comando requerido: $1"
}

env_value() {
    local key="$1"
    if [[ ! -f .env ]]; then
        return 1
    fi

    grep -E "^${key}=" .env | tail -n 1 | cut -d '=' -f 2- | sed -e 's/^"//' -e 's/"$//'
}

require_command php
require_command composer
require_command npm

[[ -f .env ]] || fail "Falta .env. Copia .env.production.example a .env y ajustalo antes de desplegar."

log "Instalando dependencias PHP de produccion"
composer install --no-dev --optimize-autoloader

log "Instalando dependencias npm"
npm install

log "Compilando assets con Vite"
npm run build

if [[ -f public/hot ]]; then
    log "Eliminando public/hot para evitar Vite dev en produccion"
    rm -f public/hot
fi

DB_CONNECTION_VALUE="$(env_value DB_CONNECTION || true)"
if [[ "$DB_CONNECTION_VALUE" == "sqlite" ]]; then
    DB_DATABASE_VALUE="$(env_value DB_DATABASE || true)"
    if [[ -z "$DB_DATABASE_VALUE" ]]; then
        DB_DATABASE_VALUE="$APP_DIR/database/database.sqlite"
    fi

    if [[ "$DB_DATABASE_VALUE" != /* ]]; then
        DB_DATABASE_VALUE="$APP_DIR/$DB_DATABASE_VALUE"
    fi

    log "Preparando SQLite en $DB_DATABASE_VALUE"
    mkdir -p "$(dirname "$DB_DATABASE_VALUE")"
    touch "$DB_DATABASE_VALUE"
fi

APP_KEY_VALUE="$(env_value APP_KEY || true)"
if [[ -z "$APP_KEY_VALUE" ]]; then
    log "Generando APP_KEY"
    php artisan key:generate --force
fi

log "Ejecutando migraciones"
php artisan migrate --force

log "Creando enlace de storage"
php artisan storage:link

log "Limpiando caches previas"
php artisan optimize:clear

log "Cacheando configuracion, rutas y vistas"
php artisan config:cache
php artisan route:cache
php artisan view:cache

log "Despliegue completado"

