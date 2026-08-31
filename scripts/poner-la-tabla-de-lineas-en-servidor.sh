#!/usr/bin/env bash
# Sales-order card Line items tab = portal PHP table.
# Run on the server: sudo bash ~/tec-tabla-lineas/install.sh
set -euo pipefail

ERP=/var/www/erp
SRC=/home/david/tec-tabla-lineas
URI=https://erp.anvfightgear.com

cp -a "$SRC/tec_portal/." "$ERP/modules/custom/tec_portal/"
install -d "$ERP/scripts"
cp "$SRC/scripts/la-ficha-usa-la-tabla-del-portal.php" "$ERP/scripts/"

chown -R www-data:www-data \
  "$ERP/modules/custom/tec_portal" \
  "$ERP/scripts/la-ficha-usa-la-tabla-del-portal.php"
echo COPIED

cd "$ERP"
D=(sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI")
"${D[@]}" cr
"${D[@]}" scr scripts/la-ficha-usa-la-tabla-del-portal.php
"${D[@]}" cr
echo DONE
