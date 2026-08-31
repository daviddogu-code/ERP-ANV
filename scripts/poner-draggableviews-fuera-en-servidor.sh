#!/usr/bin/env bash
# Uninstall draggableviews. It has not ordered anything since 19 Aug.
# Run on the server: sudo bash ~/tec-draggableviews/install.sh
# Do not composer install here (Windows has no `patch`; this is uninstall only).
# Do not config:import: local core.extension still has eca_ui, production does not.
set -euo pipefail

ERP=/var/www/erp
SRC=/home/david/tec-draggableviews
URI=https://erp.anvfightgear.com

cd "$ERP"
D=(sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI")

if sudo -u www-data php vendor/bin/drush.php --root="$ERP" --uri="$URI" pm:list --status=enabled --filter=draggableviews | grep -q draggableviews; then
  "${D[@]}" pm:uninstall draggableviews -y
  echo UNINSTALLED
else
  echo ALREADY_OFF
fi

rm -rf "$ERP/modules/contrib/draggableviews"
echo DIR_GONE

# Manifests so the next composer install does not bring the module back.
cp "$SRC/composer.json" "$ERP/composer.json"
cp "$SRC/composer.lock" "$ERP/composer.lock"

# Keep config/sync in step so a later cim does not re-enable it.
# Edit in place: do not overwrite core.extension from local.
sed -i '/^  draggableviews: 0$/d' "$ERP/config/sync/core.extension.yml"
for role in user.role.tec_executive.yml user.role.tec_manager.yml user.role.tec_supervisor.yml; do
  sed -i '/^    - draggableviews$/d' "$ERP/config/sync/$role"
  sed -i "/^  - 'access draggableviews'$/d" "$ERP/config/sync/$role"
done

install -d "$ERP/scripts"
cp "$SRC/scripts/comprobacion.php" "$ERP/scripts/comprobacion.php"
cp "$SRC/scripts/la-marca-manda-en-el-orden.php" "$ERP/scripts/la-marca-manda-en-el-orden.php"
cp "$SRC/scripts/el-modulo-draggableviews-esta-fuera.php" "$ERP/scripts/el-modulo-draggableviews-esta-fuera.php"
chown www-data:www-data \
  "$ERP/scripts/comprobacion.php" \
  "$ERP/scripts/la-marca-manda-en-el-orden.php" \
  "$ERP/scripts/el-modulo-draggableviews-esta-fuera.php"

"${D[@]}" cr
"${D[@]}" scr scripts/el-modulo-draggableviews-esta-fuera.php
echo DONE
