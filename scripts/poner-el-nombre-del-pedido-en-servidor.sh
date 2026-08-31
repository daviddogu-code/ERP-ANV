#!/usr/bin/env bash
# Sales order names on /o open /o/order/{id}.
# Run on the server: sudo bash ~/tec-nombre-pedido/install.sh
set -euo pipefail

ERP=/var/www/erp
SRC=/home/david/tec-nombre-pedido
URI=https://erp.anvfightgear.com

cp -a "$SRC/tec_portal/." "$ERP/modules/custom/tec_portal/"
install -d "$ERP/scripts"
cp "$SRC/scripts/el-nombre-del-pedido-abre-el-formulario.php" "$ERP/scripts/"

chown -R www-data:www-data \
  "$ERP/modules/custom/tec_portal" \
  "$ERP/scripts/el-nombre-del-pedido-abre-el-formulario.php"
echo COPIED

cd "$ERP"
D=(sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI")
"${D[@]}" cr
"${D[@]}" scr scripts/el-nombre-del-pedido-abre-el-formulario.php
"${D[@]}" cr
echo DONE
