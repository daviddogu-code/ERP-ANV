#!/usr/bin/env bash
# Pone el cron del sistema en erp.anvfightgear.com, lo lanza una vez y borra
# las fichas PRUEBA que viajaron con el volcado. Ejecutar EN EL SERVIDOR como david.
set -euo pipefail

ERP="/var/www/erp"
URI="https://erp.anvfightgear.com"

drush() {
  sudo -u www-data php "$ERP/vendor/bin/drush.php" --root="$ERP" --uri="$URI" "$@"
}

echo "=== Cron del ERP + limpieza de pruebas ==="

echo "--- 1. Carpeta de registro ---"
sudo mkdir -p /var/log/erp
sudo chown www-data:www-data /var/log/erp

echo "--- 2. /etc/cron.d/erp-cron ---"
sudo tee /etc/cron.d/erp-cron > /dev/null <<'EOF'
SHELL=/bin/sh
PATH=/usr/bin:/bin

*/15 * * * * www-data /usr/bin/php /var/www/erp/vendor/bin/drush.php --root=/var/www/erp --uri=https://erp.anvfightgear.com core:cron >> /var/log/erp/cron.log 2>&1
EOF
sudo chmod 644 /etc/cron.d/erp-cron
echo "    puesto:"
cat /etc/cron.d/erp-cron

echo "--- 3. logrotate ---"
sudo tee /etc/logrotate.d/erp-cron > /dev/null <<'EOF'
/var/log/erp/cron.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    create 644 www-data www-data
}
EOF

echo "--- 4. Primera pasada de cron ---"
cd "$ERP"
drush core:cron
echo "    hecha"

echo "--- 5. Borrar fichas PRUEBA ---"
drush scr scripts/borrar-pruebas-color-en-ficha.php

echo "--- 6. Comprobar ---"
systemctl is-active cron >/dev/null && echo "    servicio cron: activo" || echo "    servicio cron: INACTIVO"
drush status --fields=drupal-version,bootstrap
HORAS=$(drush php:eval 'echo round((time() - (int) \Drupal::state()->get("system.cron_last", 0)) / 3600, 1);')
echo "    ultimo cron: hace ${HORAS} horas (esperado ~0)"

echo
echo "=== HECHO. El cron volvera a correr solo cada 15 minutos. ==="
