#!/usr/bin/env bash
# Deja PO CONTROL en el menu y en /start. Ejecutar EN EL SERVIDOR como david.
set -euo pipefail

ERP="/var/www/erp"
PRIVATE="/var/www/erp-private"
URI="https://erp.anvfightgear.com"
PNG="$HOME/deploy-2026-08-20/isometric-po-control-100.png"

drush() {
  sudo -u www-data php "$ERP/vendor/bin/drush.php" --root="$ERP" --uri="$URI" "$@"
}

echo "=== PO CONTROL en el servidor ==="

if [[ ! -s "$PNG" ]]; then
  echo "Falta $PNG"
  exit 1
fi

echo "--- 1. Imagen privada ---"
sudo mkdir -p "$PRIVATE/2026-08"
sudo cp "$PNG" "$PRIVATE/2026-08/isometric-po-control-100.png"
sudo rm -f "$PRIVATE/styles/thumbnail/private/2026-08/isometric-po-control-100.png"
sudo chown www-data:www-data "$PRIVATE/2026-08/isometric-po-control-100.png"
echo "    copiada (100x100, transparente)"

echo "--- 2. Drupal ---"
cd "$ERP"
drush image:flush thumbnail || true
drush cr
drush scr scripts/poner-el-icono-de-po-control.php -- --de-verdad

echo "--- 3. Comprobar ---"
drush php:eval 'echo "menu=" . (string) \Drupal::service("plugin.manager.menu.link")->getDefinition("tec_production.purchase_queue")["title"] . "\n"; $nids = \Drupal::entityQuery("node")->accessCheck(FALSE)->condition("type","tec_landing_page")->condition("title","PO CONTROL")->execute(); echo "icono nid=" . implode(",", $nids) . "\n";'

echo
echo "=== HECHO. Prueba https://erp.anvfightgear.com/start ==="
