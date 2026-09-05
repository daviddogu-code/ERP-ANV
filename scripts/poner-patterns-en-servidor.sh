#!/usr/bin/env bash
# Factory Patterns into /var/www/erp. No config:import. No DB dump.
# Run on the server: sudo bash ~/tec-patterns/install.sh
set -euo pipefail

ERP=/var/www/erp
PRIVATE=/var/www/erp-private
SRC=/home/david/tec-patterns
URI=https://erp.anvfightgear.com

if [[ ! -d "$SRC/modules/custom/tec_inventory" ]]; then
  echo "Falta $SRC/modules/custom/tec_inventory (extrae el tar primero)"
  exit 1
fi

echo "--- 1. Codigo ---"
rsync -a --delete \
  "$SRC/modules/custom/tec_inventory/" \
  "$ERP/modules/custom/tec_inventory/"
install -d "$ERP/scripts"
cp "$SRC/scripts/poner-el-icono-de-patterns.php" "$ERP/scripts/poner-el-icono-de-patterns.php"
chown -R www-data:www-data "$ERP/modules/custom/tec_inventory"
chown www-data:www-data "$ERP/scripts/poner-el-icono-de-patterns.php"
echo COPIED

echo "--- 2. Icono privado ---"
install -d -m 775 -o www-data -g www-data "$PRIVATE/2026-09"
if [[ -s "$SRC/isometric-patterns-100.png" ]]; then
  install -m 644 -o www-data -g www-data \
    "$SRC/isometric-patterns-100.png" \
    "$PRIVATE/2026-09/isometric-patterns-100.png"
  echo "    PNG copiado"
else
  echo "    AVISO: no esta isometric-patterns-100.png"
fi

echo "--- 3. Drupal ---"
cd "$ERP"
D=(sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI")
"${D[@]}" updatedb -y
"${D[@]}" cr
if [[ -s "$PRIVATE/2026-09/isometric-patterns-100.png" ]]; then
  "${D[@]}" scr scripts/poner-el-icono-de-patterns.php -- --de-verdad
  "${D[@]}" cr
fi
"${D[@]}" php:eval 'echo "schema tec_inventory=" . \Drupal::keyValue("system.schema")->get("tec_inventory") . "\n";'
echo
echo "=== HECHO. Prueba https://erp.anvfightgear.com/pattern ==="
