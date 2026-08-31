#!/usr/bin/env bash
# Rewrite live Views: sales Open is /o/order, print after Confirm, /o/draft off.
# Run on the server: sudo bash ~/tec-vistas-pedido/install.sh
set -euo pipefail

ERP=/var/www/erp
SRC=/home/david/tec-vistas-pedido
URI=https://erp.anvfightgear.com

cp -a "$SRC/tec_portal/." "$ERP/modules/custom/tec_portal/"
install -d "$ERP/scripts"
cp "$SRC/scripts/poner-vistas-de-pedido-en-servidor.php" "$ERP/scripts/"

chown -R www-data:www-data \
  "$ERP/modules/custom/tec_portal" \
  "$ERP/scripts/poner-vistas-de-pedido-en-servidor.php"
echo COPIED

cd "$ERP"
D=(sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI")
"${D[@]}" cr
"${D[@]}" scr scripts/poner-vistas-de-pedido-en-servidor.php
"${D[@]}" cr
echo DONE
