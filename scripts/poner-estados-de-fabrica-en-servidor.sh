#!/usr/bin/env bash
# Copy factory status files into /var/www/erp and run the migration.
# Run on the server: sudo bash ~/tec-estados/install.sh
set -euo pipefail

ERP=/var/www/erp
SRC=/home/david/tec-estados
URI=https://erp.anvfightgear.com

mkdir -p "$ERP/modules/custom/tec_production/src/Controller"
cp "$SRC/prod/src/SalesStatus.php" "$ERP/modules/custom/tec_production/src/"
cp "$SRC/prod/src/Controller/SalesStatusController.php" "$ERP/modules/custom/tec_production/src/Controller/"
cp "$SRC/prod/src/Form/ProductionQueueForm.php" "$ERP/modules/custom/tec_production/src/Form/"
cp "$SRC/prod/css/sales-status.css" "$ERP/modules/custom/tec_production/css/"
cp "$SRC/prod/js/production-queue.js" "$ERP/modules/custom/tec_production/js/"
cp "$SRC/prod/tec_production.module" "$ERP/modules/custom/tec_production/"
cp "$SRC/prod/tec_production.install" "$ERP/modules/custom/tec_production/"
cp "$SRC/prod/tec_production.libraries.yml" "$ERP/modules/custom/tec_production/"
cp "$SRC/prod/tec_production.routing.yml" "$ERP/modules/custom/tec_production/"
cp "$SRC/portal/src/PortalOrder.php" "$ERP/modules/custom/tec_portal/src/"
cp "$SRC/portal/src/Form/OrderForm.php" "$ERP/modules/custom/tec_portal/src/Form/"
cp "$SRC/portal/tec_portal.module" "$ERP/modules/custom/tec_portal/"
cp "$SRC/portal/css/portal.css" "$ERP/modules/custom/tec_portal/css/"
cp "$SRC/sync/"*.yml "$ERP/config/sync/"
cp "$SRC/scripts/poner-estados-de-fabrica-en-servidor.php" "$ERP/scripts/"
chown -R www-data:www-data \
  "$ERP/modules/custom/tec_production" \
  "$ERP/modules/custom/tec_portal" \
  "$ERP/scripts/poner-estados-de-fabrica-en-servidor.php"
echo COPIED

cd "$ERP"
sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI" cr
sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI" scr scripts/poner-estados-de-fabrica-en-servidor.php
sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI" cr
echo DONE
