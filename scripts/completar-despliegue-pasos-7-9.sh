#!/usr/bin/env bash
# Continuar despliegue si los pasos 1-6 ya terminaron (fallo tipico: drush como david).
set -euo pipefail

ERP="/var/www/erp"
URI="https://erp.anvfightgear.com"

drush() {
  sudo -u www-data php "$ERP/vendor/bin/drush.php" --uri="$URI" "$@"
}

echo "=== Completar despliegue (pasos 7-9) ==="
cd "$ERP"

echo "--- 7. Drupal ---"
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
