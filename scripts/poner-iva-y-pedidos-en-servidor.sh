#!/usr/bin/env bash
# Copy factory Place/Confirm, sales VAT, company settings and the public PNG
# into /var/www/erp, then run 10005 + 10006.
# Run on the server: sudo bash ~/tec-pedido-2026-08-31/install.sh
set -euo pipefail

ERP=/var/www/erp
SRC=/home/david/tec-pedido-2026-08-31
URI=https://erp.anvfightgear.com

# Module trees. config/install/tec_production.settings.yml is only used on first
# install of the module; it does not overwrite the live VAT rate or home tiles.
cp -a "$SRC/tec_portal/." "$ERP/modules/custom/tec_portal/"
cp -a "$SRC/tec_production/." "$ERP/modules/custom/tec_production/"
cp -a "$SRC/tec_crm_ux/." "$ERP/modules/custom/tec_crm_ux/"

install -d -m 775 -o www-data -g www-data "$ERP/sites/default/files"
install -m 644 -o www-data -g www-data "$SRC/anv-logo.png" "$ERP/sites/default/files/anv-logo.png"

install -d "$ERP/config/sync"
cp "$SRC/sync/"*.yml "$ERP/config/sync/"
install -d "$ERP/scripts"
cp "$SRC/scripts/poner-iva-y-pedidos-en-servidor.php" "$ERP/scripts/"

chown -R www-data:www-data \
  "$ERP/modules/custom/tec_portal" \
  "$ERP/modules/custom/tec_production" \
  "$ERP/modules/custom/tec_crm_ux" \
  "$ERP/scripts/poner-iva-y-pedidos-en-servidor.php"
echo COPIED

cd "$ERP"
D=(sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI")
"${D[@]}" cr
"${D[@]}" scr scripts/poner-iva-y-pedidos-en-servidor.php
"${D[@]}" cr
echo DONE
