#!/usr/bin/env bash
# Deja Supplier Orders en /start. Ejecutar EN EL SERVIDOR como david.
set -euo pipefail

ERP="/var/www/erp"
PRIVATE="/var/www/erp-private"
URI="https://erp.anvfightgear.com"
PNG="$HOME/deploy-2026-08-30/isometric-supplier-orders-100.png"

drush() {
  sudo -u www-data php "$ERP/vendor/bin/drush.php" --root="$ERP" --uri="$URI" "$@"
}

echo "=== Supplier Orders en el servidor ==="

if [[ ! -s "$PNG" ]]; then
  echo "Falta $PNG"
  exit 1
fi

echo "--- 1. Imagen privada ---"
sudo mkdir -p "$PRIVATE/2026-08"
sudo cp "$PNG" "$PRIVATE/2026-08/isometric-supplier-orders-100.png"
sudo rm -f "$PRIVATE/styles/thumbnail/private/2026-08/isometric-supplier-orders-100.png"
sudo chown www-data:www-data "$PRIVATE/2026-08/isometric-supplier-orders-100.png"
echo "    copiada (100x100, transparente)"

echo "--- 2. Drupal ---"
cd "$ERP"
drush image:flush thumbnail || true
drush cr
drush scr scripts/poner-el-icono-de-supplier-orders.php -- --de-verdad

echo "--- 3. Comprobar ---"
drush scr scripts/comprobar-icono-supplier-orders.php

echo
echo "=== HECHO. Prueba https://erp.anvfightgear.com/start ==="
