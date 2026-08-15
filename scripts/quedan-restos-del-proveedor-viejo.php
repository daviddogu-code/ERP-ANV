<?php

/**
 * Comprueba que no queda ni un resto de `field_tec_suppliers`, el campo de
 * proveedor viejo, y que el boton "Add new material" de la pestana del proveedor
 * lleva la marca que si funciona.
 *
 * Se escribe como guion y no como `php:eval` porque PowerShell se come las barras
 * y los dolares del PHP en linea, y eso ya costo un rato una vez.
 *
 * Uso: drush php:script scripts/quedan-restos-del-proveedor-viejo
 */

$vivo = FALSE;

$almacen = \Drupal::entityTypeManager()
  ->getStorage('field_storage_config')
  ->load('taxonomy_term.field_tec_suppliers');
echo 'Almacen del campo viejo: ' . ($almacen ? 'SIGUE VIVO' : 'no existe') . PHP_EOL;
$vivo = $vivo || $almacen;

$tabla = \Drupal::database()->schema()->tableExists('taxonomy_term__field_tec_suppliers');
echo 'Su tabla de datos:       ' . ($tabla ? 'SIGUE VIVA' : 'no existe') . PHP_EOL;
$vivo = $vivo || $tabla;

// El barrido que importa: que nadie lo nombre en toda la configuracion activa. Una
// vista puede seguir nombrando un campo que ya no existe y no quejarse hasta que
// alguien abre la pantalla.
$nombran = [];
foreach (\Drupal::service('config.storage')->listAll() as $clave) {
  $crudo = \Drupal::service('config.factory')->get($clave)->getRawData();
  if (str_contains(serialize($crudo), 'field_tec_suppliers')) {
    $nombran[] = $clave;
  }
}
echo 'Configuracion que lo nombra: ' . ($nombran ? 'LO NOMBRAN ' . implode(', ', $nombran) : 'ninguna') . PHP_EOL;
$vivo = $vivo || $nombran;

// El boton. `{{ id }}` es una marca de columna de fila y una cabecera de vista no la
// ve, asi que el enlace salia con el hueco vacio. Se busca por todas las pantallas y
// por las tres zonas de texto, porque una pantalla puede heredar la zona de otra y
// entonces el enlace no esta donde uno lo busca.
$bien = TRUE;
$encontrados = 0;
foreach (\Drupal::config('views.view.tec_inventory')->get('display') ?? [] as $pantalla => $ajustes) {
  foreach (['header', 'footer', 'empty'] as $zona) {
    foreach ($ajustes['display_options'][$zona] ?? [] as $trozoDeTexto) {
      // Las dos zonas de texto de Views no guardan igual: `text` guarda un array
      // con `value` y formato, y `text_custom` guarda la cadena pelada.
      $contenido = $trozoDeTexto['content'] ?? '';
      $texto = is_array($contenido) ? (string) ($contenido['value'] ?? '') : (string) $contenido;
      if (!preg_match_all('/target_id=([^&"\']*)/', $texto, $marcas)) {
        continue;
      }
      foreach ($marcas[1] as $marca) {
        $encontrados++;
        $vale = str_contains($marca, 'raw_arguments.id');
        $bien = $bien && $vale;
        echo "Enlace en $pantalla/$zona: target_id=$marca" . ($vale ? '' : '  <-- NO RELLENA') . PHP_EOL;
      }
    }
  }
}
if (!$encontrados) {
  echo 'Marca del boton:         NO SE ENCUENTRA NINGUN ENLACE CON target_id' . PHP_EOL;
  $bien = FALSE;
}

echo PHP_EOL . ($vivo || !$bien
  ? 'MAL: queda algo por limpiar o el boton no lleva la marca buena.'
  : 'BIEN: el campo viejo no deja rastro y el boton lleva raw_arguments.id.') . PHP_EOL;
