#!/usr/bin/env bash
set -euo pipefail

### === CONFIGURACIÓN (ajústala si lo necesitas) ===
SSH_USER="root"
SSH_HOST="45.90.223.223"             # o srv979975 si tienes ese hostname
REMOTE_APP_BASE="/var/www/apps/escuela"
REMOTE_CURRENT="$REMOTE_APP_BASE/current"
REMOTE_SHARED="$REMOTE_APP_BASE/shared"
REMOTE_DUMPS="$REMOTE_APP_BASE"

DB_NAME="escuela"
DB_USER="escuela_user"
DB_PASS="MiPasswordSeguro123"

# Archivo dump local (ya lo tienes creado)
LOCAL_DUMP_FILE="${1:-escuela_reportes_dump.sql}"  # puedes pasar otro por parámetro

### === FIN CONFIG ===

if [[ ! -f "$LOCAL_DUMP_FILE" ]]; then
  echo "❌ No encuentro el dump local: $LOCAL_DUMP_FILE"
  echo "   Pásalo como parámetro o genera el archivo en la raíz del proyecto."
  exit 1
fi

echo "➡️  Conectando a $SSH_USER@$SSH_HOST ..."
# Pedimos password de root de MySQL REMOTO de forma interactiva (no se guarda)
read -s -p "🔐 Password de MySQL root (servidor remoto): " MYSQL_ROOT_PWD
echo

TS=$(date +"%Y%m%d_%H%M%S")
REMOTE_BACKUP="$REMOTE_DUMPS/backup_${DB_NAME}_${TS}.sql"
REMOTE_UPLOAD="$REMOTE_DUMPS/upload_${DB_NAME}_${TS}.sql"

echo "➡️  Subiendo dump a servidor: $REMOTE_UPLOAD"
scp "$LOCAL_DUMP_FILE" "${SSH_USER}@${SSH_HOST}:$REMOTE_UPLOAD"

echo "➡️  Respaldando BD remota actual en: $REMOTE_BACKUP"
ssh "${SSH_USER}@${SSH_HOST}" "mysqldump -uroot -p'${MYSQL_ROOT_PWD}' ${DB_NAME} > '${REMOTE_BACKUP}' || true"

echo "➡️  Recreando base de datos ${DB_NAME}"
ssh "${SSH_USER}@${SSH_HOST}" "mysql -uroot -p'${MYSQL_ROOT_PWD}' -e \"
  DROP DATABASE IF EXISTS ${DB_NAME};
  CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""

echo "➡️  Importando dump a ${DB_NAME} (esto puede tardar)"
ssh "${SSH_USER}@${SSH_HOST}" "mysql -uroot -p'${MYSQL_ROOT_PWD}' ${DB_NAME} < '${REMOTE_UPLOAD}'"

echo "➡️  Asegurando usuario de la app: ${DB_USER}"
ssh "${SSH_USER}@${SSH_HOST}" "mysql -uroot -p'${MYSQL_ROOT_PWD}' -e \"
  CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
  GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
  FLUSH PRIVILEGES;\""

echo "➡️  Corriendo tareas de Laravel (migraciones y caches)"
ssh "${SSH_USER}@${SSH_HOST}" "cd '${REMOTE_CURRENT}' && \
  php artisan migrate --force && \
  php artisan config:cache && \
  php artisan storage:link || true"

echo "🧹 Limpiando archivo subido"
ssh "${SSH_USER}@${SSH_HOST}" "rm -f '${REMOTE_UPLOAD}'"

echo "✅ Listo. Respaldo: ${REMOTE_BACKUP}"
