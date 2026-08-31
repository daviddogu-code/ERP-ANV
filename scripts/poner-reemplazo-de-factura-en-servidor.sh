#!/usr/bin/env bash
# El icono de factura en /supplier-orders abre el dialogo de archivar, para
# sustituir o quitar el archivo despues de Closed. Ejecutar EN EL SERVIDOR
# como david, despues de haber copiado el codigo.
set -euo pipefail

ERP="/var/www/erp"
URI="https://erp.anvfightgear.com"

drush() {
  sudo -u www-data php "$ERP/vendor/bin/drush.php" --root="$ERP" --uri="$URI" "$@"
}

echo "=== Facturas: sustituir y quitar desde Supplier Orders ==="

cd "$ERP"
drush cr

echo
echo "=== HECHO. File invoice deja INV_PO_30082026_POLYTE_26-013.pdf ==="
