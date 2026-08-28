#!/usr/bin/env bash
# Reemplazo total del ERP en erp.anvfightgear.com desde paquetes en ~/deploy-2026-08-20/
# Ejecutar EN EL SERVIDOR como david. Pide sudo cuando haga falta.
set -euo pipefail

DEPLOY="$HOME/deploy-2026-08-20"
ERP="/var/www/erp"
PRIVATE="/var/www/erp-private"
URI="https://erp.anvfightgear.com"

drush() {
  sudo -u www-data php "$ERP/vendor/bin/drush.php" --uri="$URI" "$@"
}

echo "=== Despliegue ERP ANV — reemplazo total ==="
echo "Paquetes en: $DEPLOY"
echo

for f in actatec.sql tec-code.tar.gz tec-files.tar.gz; do
  if [[ ! -s "$DEPLOY/$f" ]]; then
    echo "FALTA o vacio: $DEPLOY/$f"
    exit 1
  fi
done

echo "--- 1. Credenciales BD (settings.php) ---"
DB_NAME=$(sudo awk "/'database' =>/ {print \$3}" "$ERP/sites/default/settings.php" | tr -d "',")
DB_USER=$(sudo awk "/'username' =>/ {print \$3}" "$ERP/sites/default/settings.php" | tr -d "',")
DB_PASS=$(sudo awk "/'password' =>/ {print \$3}" "$ERP/sites/default/settings.php" | tr -d "',")
DB_HOST=$(sudo awk "/'host' =>/ {print \$3}" "$ERP/sites/default/settings.php" | tr -d "',")
echo "    BD: $DB_NAME @ $DB_HOST user $DB_USER"

echo "--- 2. Codigo (sin tocar settings.php) ---"
TMP=$(mktemp -d)
# tec-code.tar.gz se crea en Windows: sites/default sale dr-xr-xr-x (SCHILY.fflags)
# y tar no puede escribir ahi. Extraemos el resto y luego solo los defaults.
tar -xzf "$DEPLOY/tec-code.tar.gz" -C "$TMP" --exclude='./sites/default' || true
mkdir -p "$TMP/sites/default"
tar -xzf "$DEPLOY/tec-code.tar.gz" -C "$TMP" \
  ./sites/default/default.services.yml \
  ./sites/default/default.settings.php || true
rm -f "$TMP/sites/default/settings.php"
rsync -a --delete \
  --exclude='sites/default/settings.php' \
  --exclude='sites/default/files/' \
  --exclude='sites/default/private/' \
  --exclude='vendor/' \
  --exclude='core/' \
  --exclude='modules/contrib/' \
  --exclude='themes/contrib/' \
  "$TMP/" "$ERP/"
rm -rf "$TMP"

echo "--- 3. Composer ---"
cd "$ERP"
rm -rf vendor core modules/contrib themes/contrib
composer install --no-interaction --no-dev
CONTRIB=$(ls modules/contrib 2>/dev/null | wc -l)
echo "    modules/contrib: $CONTRIB (esperado ~100)"

echo "--- 4. Parche inline_entity_form (composer-patches) ---"
grep -q '?? \[\]' "$ERP/modules/contrib/inline_entity_form/inline_entity_form.module" \
  && echo "    parche OK" || { echo "    parche FALLO"; exit 1; }

echo "--- 5. Base de datos (reemplazo total) ---"
sudo mysql -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql "$DB_NAME" < "$DEPLOY/actatec.sql"
echo "    importada"

echo "--- 6. Ficheros ---"
FILETMP=$(mktemp -d)
tar -xzf "$DEPLOY/tec-files.tar.gz" -C "$FILETMP"
sudo rsync -a --delete "$FILETMP/files/" "$ERP/sites/default/files/"
sudo rsync -a --delete "$FILETMP/private/" "$PRIVATE/"
rm -rf "$FILETMP"
sudo chown -R www-data:www-data "$ERP/sites/default/files" "$PRIVATE"
echo "    files + private restaurados"

echo "--- 7. Drupal ---"
cd "$ERP"
drush updatedb -y
drush cim -y
drush cr

echo "--- 8. Post-despliegue ---"
drush scr scripts/encender-el-control-de-compras.php -- --aplicar || true
drush scr scripts/comprobacion.php || true

echo "--- 9. Comprobaciones ---"
drush status --fields=drupal-version,bootstrap
ECA=$(drush sql:query "SELECT COUNT(*) FROM config WHERE name LIKE 'eca.eca.%'" 2>/dev/null | tr -d '[:space:]')
echo "    procesos ECA: $ECA (esperado 30)"

echo
echo "=== HECHO. Prueba https://erp.anvfightgear.com/user/login ==="
