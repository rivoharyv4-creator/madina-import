#!/bin/sh
set -eu

fail() {
    echo "[madina] ERREUR: $1" >&2
    exit 1
}

VOLUME_PATH="${RAILWAY_VOLUME_MOUNT_PATH:-}"
DB_PATH="${DB_DATABASE:-}"
FILES_PATH="${PERSISTENT_STORAGE_PATH:-}"
BACKUPS_PATH="${BACKUP_PATH:-}"

[ "${APP_ENV:-}" = "production" ] || fail "APP_ENV=production est obligatoire sur Railway."
[ -n "${APP_KEY:-}" ] || fail "APP_KEY est absent. Générez-le avant le déploiement."
[ "$VOLUME_PATH" = "/data" ] || fail "Le volume Railway doit être monté sur /data (RAILWAY_VOLUME_MOUNT_PATH=$VOLUME_PATH)."
[ "$DB_PATH" = "/data/madina-import.sqlite" ] || fail "DB_DATABASE doit être /data/madina-import.sqlite."
[ "$FILES_PATH" = "/data/files" ] || fail "PERSISTENT_STORAGE_PATH doit être /data/files."
[ "$BACKUPS_PATH" = "/data/backups" ] || fail "BACKUP_PATH doit être /data/backups."
[ -d /data ] || fail "Le volume /data n'est pas monté."
[ -w /data ] || fail "Le volume /data n'est pas inscriptible."

umask 027
mkdir -p \
    /data/files/products \
    /data/files/payments \
    /data/files/expenses \
    /data/files/invoices \
    /data/files/quotes \
    /data/files/logistics \
    /data/files/exports \
    /data/backups
chmod 0770 /data/files /data/files/products /data/files/payments /data/files/expenses /data/files/invoices /data/files/quotes /data/files/logistics /data/files/exports /data/backups

if [ ! -e "$DB_PATH" ]; then
    : > "$DB_PATH"
    chmod 0660 "$DB_PATH"
    echo "[madina] Nouvelle base SQLite créée."
fi

[ -f "$DB_PATH" ] || fail "DB_DATABASE n'est pas un fichier régulier."
[ -r "$DB_PATH" ] && [ -w "$DB_PATH" ] || fail "La base SQLite n'est pas lisible et inscriptible."

php artisan madina:prepare-storage --no-interaction
php artisan madina:sqlite-configure --no-interaction
php artisan migrate --force --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction

php artisan schedule:work --no-interaction &

echo "[madina] Démarrage sur le port ${PORT:-8080}."
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-interaction
