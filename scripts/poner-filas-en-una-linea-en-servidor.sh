#!/usr/bin/env bash
# Deja las filas de /supplier-orders en una sola linea. Ejecutar EN EL SERVIDOR
# como david, despues de haber copiado el codigo.
set -euo pipefail

ERP="/var/www/erp"
URI="https://erp.anvfightgear.com"

drush() {
  sudo -u www-data php "$ERP/vendor/bin/drush.php" --root="$ERP" --uri="$URI" "$@"
}

echo "=== Supplier Orders: una linea por fila ==="

cd "$ERP"
drush cr
drush scr scripts/poner-las-filas-en-una-linea.php

echo
echo "=== HECHO. Prueba https://erp.anvfightgear.com/supplier-orders ==="
