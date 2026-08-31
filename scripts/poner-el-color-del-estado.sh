#!/usr/bin/env bash
set -euo pipefail
ERP=/var/www/erp
SRC=/home/david/tec-color-estado
URI=https://erp.anvfightgear.com

cp "$SRC/css/sales-status.css" "$ERP/modules/custom/tec_production/css/"
cp "$SRC/tec_production.module" "$ERP/modules/custom/tec_production/"
cp "$SRC/tec_production.install" "$ERP/modules/custom/tec_production/"
install -d "$ERP/scripts"
cp "$SRC/scripts/poner-el-color-del-estado.php" "$ERP/scripts/"
chown www-data:www-data \
  "$ERP/modules/custom/tec_production/css/sales-status.css" \
  "$ERP/modules/custom/tec_production/tec_production.module" \
  "$ERP/modules/custom/tec_production/tec_production.install" \
  "$ERP/scripts/poner-el-color-del-estado.php"
echo COPIED

cd "$ERP"
D=(sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI")
"${D[@]}" cr
"${D[@]}" scr scripts/poner-el-color-del-estado.php
"${D[@]}" cr
echo DONE
