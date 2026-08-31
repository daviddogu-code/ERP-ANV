#!/usr/bin/env bash
# Printed proforma is PHP at /o/pf/{id}/print.
# Run on the server: sudo bash ~/tec-proforma/install.sh
set -euo pipefail

ERP=/var/www/erp
SRC=/home/david/tec-proforma
URI=https://erp.anvfightgear.com

cp -a "$SRC/tec_portal/." "$ERP/modules/custom/tec_portal/"
install -d "$ERP/scripts"
cp "$SRC/scripts/el-print-de-la-proforma.php" "$ERP/scripts/"

chown -R www-data:www-data \
  "$ERP/modules/custom/tec_portal" \
  "$ERP/scripts/el-print-de-la-proforma.php"
echo COPIED

cd "$ERP"
D=(sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI")
"${D[@]}" cr
"${D[@]}" scr scripts/el-print-de-la-proforma.php
"${D[@]}" cr
echo DONE
