#!/usr/bin/env bash
# Pone el enlace de descarga de facturas en /supplier-orders y en la ficha.
# Ejecutar EN EL SERVIDOR como david, despues de haber copiado el codigo.
set -euo pipefail

ERP="/var/www/erp"
URI="https://erp.anvfightgear.com"
PNG="$HOME/deploy-2026-08-30/000-invoice-16.png"

drush() {
  sudo -u www-data php "$ERP/vendor/bin/drush.php" --root="$ERP" --uri="$URI" "$@"
}

echo "=== Facturas: enlace de descarga ==="

if [[ ! -s "$PNG" ]]; then
  echo "Falta $PNG"
  exit 1
fi

echo "--- 1. Icono publico ---"
sudo cp "$PNG" "$ERP/sites/default/files/000-invoice-16.png"
sudo chown www-data:www-data "$ERP/sites/default/files/000-invoice-16.png"
echo "    copiada"

echo "--- 2. Drupal ---"
cd "$ERP"
drush cr
drush scr scripts/poner-el-enlace-de-la-factura.php

echo
echo "=== HECHO. Prueba https://erp.anvfightgear.com/supplier-orders ==="
