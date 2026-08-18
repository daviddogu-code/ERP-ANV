<?php

/**
 * @file
 * Quien lee el cliente del producto, y cual de esos sitios esta vivo.
 *
 *   php vendor\bin\drush.php scr scripts/quien-lee-el-cliente-del-producto.php
 *
 * `field_tec_customer` en `tec_product` es el campo que hay que retirar para
 * cerrar el modelo de marcas: los productos van dentro de la marca, y quien
 * compra la marca lo dice el cliente en su ficha desde el 19 de agosto de 2026.
 *
 * Antes de cortar hay que saber quien lo lee, y sobre todo cuales de esos
 * lectores estan VIVOS, porque contar menciones da un numero mucho mas grande
 * que contar trabajo. Una pantalla puede estar en la configuracion y no ser
 * alcanzable por nadie: un bloque cuya unica puerta era un quicktabs que nadie
 * ha colocado, o una pantalla apagada, es recableado que no hay que hacer.
 *
 * La liveza se decide subiendo la cadena hasta encontrar una puerta de verdad, y
 * mirando en DOS sitios, que es lo que se aprendio escribiendo esto:
 *
 *   - La configuracion: bloques colocados, campos que embeben vistas,
 *     navegadores de entidades, quicktabs, ECAs encendidas, rutas.
 *   - Y los datos, que es el agujero de mirar solo configuracion. Las maquetas
 *     de las paginas del ERP viven en la ficha de cada pagina, no en la
 *     configuracion, y ahi hay bloques de vista metidos a mano. Mirando solo la
 *     configuracion, el bloque de la pantalla /p salia muerto y esta vivo.
 *
 * El pedido tiene otro campo que se llama igual -quien compra ese pedido- y no
 * tiene nada que ver: de el cuelgan la numeracion, el IVA y los precios. Se
 * distinguen por la tabla, no por el nombre.
 *
 * Solo mira. No cambia nada.
 */

const CAMPO = 'field_tec_customer';
const TABLA = 'tec_product__field_tec_customer';

$gestor = \Drupal::entityTypeManager();
$almacenConfig = \Drupal::service('config.storage');
$rutas = \Drupal::service('router.route_provider');
$bd = \Drupal::database();

$vivos = 0;
$muertos = 0;

$aTexto = fn(array $datos): string => (string) json_encode($datos, JSON_INVALID_UTF8_SUBSTITUTE);

/**
 * Toda la configuracion activa en texto, para poder preguntar quien nombra a que.
 */
$todo = [];
foreach ($almacenConfig->listAll() as $nombre) {
  $todo[$nombre] = $aTexto(\Drupal::config($nombre)->getRawData());
}

// ---------------------------------------------------------------------------
// Los bloques metidos en maquetas, que no son configuracion.
// ---------------------------------------------------------------------------
$enLosDatos = [];
$columnasMiradas = 0;
foreach ($bd->schema()->findTables('%layout_builder__layout') as $tabla) {
  if (str_contains($tabla, '_revision__') || !$bd->schema()->fieldExists($tabla, 'entity_id')) {
    continue;
  }
  $columnasMiradas++;
  $tipoDeLaTabla = strstr($tabla, '__', TRUE);
  foreach ($bd->select($tabla, 't')->fields('t', ['entity_id'])->distinct()->execute()->fetchCol() as $numero) {
    $ficha = $gestor->getStorage($tipoDeLaTabla)->load($numero);
    if (!$ficha || !$ficha->hasField('layout_builder__layout')) {
      continue;
    }
    foreach ($ficha->get('layout_builder__layout') as $trozo) {
      foreach ($trozo->section?->getComponents() ?? [] as $componente) {
        $enLosDatos[$componente->getPluginId()] = sprintf('la maqueta de %s %s «%s»',
          $tipoDeLaTabla, $numero, mb_substr((string) $ficha->label(), 0, 24));
      }
    }
  }
}

/**
 * Que objetos de configuracion nombran a una pantalla concreta de una vista.
 */
$quienNombra = function (string $vista, string $pantalla) use ($todo): array {
  $encontrados = [];
  foreach ($todo as $nombre => $texto) {
    if ($nombre === 'views.view.' . $vista) {
      continue;
    }
    if ($pantalla === 'default') {
      // «default» aparece en media configuracion del sitio, asi que aqui no vale
      // buscar la palabra suelta. Se busca la pareja tal como la escriben las
      // acciones de ECA, que son las que consultan la pantalla maestra.
      if (str_contains($texto, '"view_id":"' . $vista . '"')
        && str_contains($texto, '"display_id":"default"')) {
        $encontrados[] = $nombre;
      }
      continue;
    }
    $pegado = str_contains($texto, $vista . '-' . $pantalla);
    $sueltos = str_contains($texto, '"' . $vista . '"') && str_contains($texto, '"' . $pantalla . '"');
    if ($pegado || $sueltos) {
      $encontrados[] = $nombre;
    }
  }

  return $encontrados;
};

/**
 * Si un objeto de configuracion tiene por donde entrar, y por donde.
 *
 * Aqui esta el meollo. Un bloque de vista puede estar «usado» por un quicktabs y
 * no ser alcanzable, porque ese quicktabs no esta colocado en ninguna region: la
 * cadena se corta un eslabon mas arriba y hay que subir a mirarlo.
 *
 * @return array
 *   [si se alcanza, la cadena en palabras].
 */
$puerta = function (string $config, int $vueltas = 0) use (&$puerta, $todo, $enLosDatos): array {
  if ($vueltas > 3) {
    return [TRUE, $config . ' (no sigo subiendo)'];
  }
  $datos = \Drupal::config($config)->getRawData();
  $id = substr($config, strrpos($config, '.') + 1);

  if (str_starts_with($config, 'block.block.')) {
    $encendido = (bool) ($datos['status'] ?? FALSE);
    return [$encendido, sprintf('bloque colocado en %s/%s%s',
      $datos['theme'] ?? '?', $datos['region'] ?? '?', $encendido ? '' : ', APAGADO')];
  }
  if (str_starts_with($config, 'field.field.')) {
    return [TRUE, sprintf('campo de %s.%s', $datos['entity_type'] ?? '?', $datos['bundle'] ?? '?')];
  }
  if (str_starts_with($config, 'core.entity_view_display.') || str_starts_with($config, 'core.entity_form_display.')) {
    return [TRUE, sprintf('pantalla de %s.%s', $datos['targetEntityType'] ?? '?', $datos['bundle'] ?? '?')];
  }
  if (str_starts_with($config, 'eca.eca.')) {
    $encendida = (bool) ($datos['status'] ?? FALSE);
    return [$encendida, sprintf('la ECA "%s"%s', mb_substr((string) ($datos['label'] ?? $id), 0, 40),
      $encendida ? '' : ', APAGADA')];
  }

  // Cosas que a su vez necesitan que alguien las ensene.
  $comoSeNombran = [
    'quicktabs.quicktabs_instance.' => ['quicktabs_block:' . $id],
    'entity_browser.browser.' => ['"entity_browser":"' . $id . '"', 'entity_browser:' . $id],
  ];
  foreach ($comoSeNombran as $prefijo => $patrones) {
    if (!str_starts_with($config, $prefijo)) {
      continue;
    }
    foreach ($patrones as $patron) {
      if (isset($enLosDatos[$patron])) {
        return [TRUE, $id . ' <- ' . $enLosDatos[$patron]];
      }
    }
    foreach ($todo as $nombre => $texto) {
      if ($nombre === $config) {
        continue;
      }
      foreach ($patrones as $patron) {
        if (!str_contains($texto, $patron)) {
          continue;
        }
        [$vive, $cadena] = $puerta($nombre, $vueltas + 1);
        if ($vive) {
          return [TRUE, $id . ' <- ' . $cadena];
        }
      }
    }

    return [FALSE, $id . ' <- nadie lo ensena'];
  }

  return [TRUE, $config];
};

/**
 * Las opciones de una pantalla, con lo que herede de la maestra ya resuelto.
 */
$opciones = function (array $vista, string $pantalla, string $seccion) {
  $suyo = $vista['display'][$pantalla]['display_options'] ?? [];
  $hereda = ($suyo['defaults'][$seccion] ?? TRUE) !== FALSE;
  if ($pantalla === 'default' || !$hereda) {
    return $suyo[$seccion] ?? [];
  }

  return $vista['display']['default']['display_options'][$seccion] ?? [];
};

print "\n";
print "=====================================================================\n";
print "  Quien lee el cliente del producto\n";
print "=====================================================================\n";

// ---------------------------------------------------------------------------
// 1. Las vistas.
// ---------------------------------------------------------------------------
foreach ($almacenConfig->listAll('views.view.') as $nombreVista) {
  $vista = \Drupal::config($nombreVista)->getRawData();
  if (!str_contains($aTexto($vista), TABLA)) {
    continue;
  }

  printf("\n  %s  (%s)\n", $vista['id'], $vista['label'] ?? '');
  print '  ' . str_repeat('=', 66) . "\n";

  foreach ($vista['display'] as $clave => $pantalla) {
    // Como lo usa. Esto es la medida del trabajo: una columna se quita, un
    // argumento hay que cambiarlo por otro, un filtro hay que repensarlo.
    $usos = [];
    foreach ([
      'fields' => 'una columna',
      'filters' => 'un filtro',
      'arguments' => 'lo recibe por la direccion',
      'relationships' => 'salta al cliente',
      'sorts' => 'ordena por el',
    ] as $seccion => $comoSeLlama) {
      foreach ($opciones($vista, $clave, $seccion) as $id => $definicion) {
        $suTabla = is_array($definicion) ? ($definicion['table'] ?? '') : '';
        $porRelacion = is_array($definicion) && ($definicion['relationship'] ?? '') === CAMPO;
        if ($suTabla === TABLA || $porRelacion || ($suTabla === '' && str_starts_with((string) $id, CAMPO))) {
          $usos[] = $comoSeLlama;
          break;
        }
      }
    }
    foreach (['header', 'footer', 'empty'] as $seccion) {
      foreach ($opciones($vista, $clave, $seccion) as $trozo) {
        if (is_array($trozo) && str_contains($aTexto($trozo), CAMPO)) {
          $usos[] = 'un enlace escrito a mano en ' . $seccion;
          break 2;
        }
      }
    }

    if ($usos === []) {
      continue;
    }

    $tipoPantalla = $pantalla['display_plugin'] ?? '?';
    $titulo = $pantalla['display_title'] ?? $clave;

    // Y quien la alcanza.
    $veredicto = 'MUERTO';
    $prueba = 'nadie la nombra';
    if (($pantalla['display_options']['enabled'] ?? TRUE) === FALSE) {
      $prueba = 'la pantalla esta apagada';
    }
    elseif (in_array($tipoPantalla, ['page', 'feed'], TRUE)) {
      $camino = $pantalla['display_options']['path'] ?? '';
      try {
        $rutas->getRouteByName('view.' . $vista['id'] . '.' . $clave);
        $veredicto = 'VIVO';
        $prueba = '/' . $camino;
      }
      catch (\Throwable $e) {
        $prueba = 'sin ruta';
      }
    }
    elseif ($tipoPantalla === 'attachment') {
      $padres = array_filter((array) ($pantalla['display_options']['displays'] ?? []));
      $veredicto = $padres ? 'VIVO' : 'MUERTO';
      $prueba = $padres ? 'pegada a ' . implode(', ', array_keys($padres)) : 'no esta pegada a ninguna';
    }
    elseif (isset($enLosDatos['views_block:' . $vista['id'] . '-' . $clave])) {
      // La puerta que la configuracion no cuenta.
      $veredicto = 'VIVO';
      $prueba = $enLosDatos['views_block:' . $vista['id'] . '-' . $clave];
    }
    else {
      $caminos = [];
      foreach ($quienNombra($vista['id'], $clave) as $nombran) {
        [$vive, $cadena] = $puerta($nombran);
        $caminos[] = ($vive ? '' : '(cerrado) ') . $cadena;
        if ($vive) {
          $veredicto = 'VIVO';
        }
      }
      if ($caminos !== []) {
        $prueba = ($veredicto === 'MUERTO' ? 'la nombran, pero: ' : '')
          . implode(' | ', array_slice($caminos, 0, 2));
      }
    }

    $veredicto === 'VIVO' ? $vivos++ : $muertos++;

    printf("    %-7s %-16s %-28s %s\n", $veredicto, $clave, mb_substr((string) $titulo, 0, 27), $prueba);
    printf("            %s\n", implode(' + ', array_unique($usos)));
  }
}

// ---------------------------------------------------------------------------
// 2. El formulario y las fichas del producto.
// ---------------------------------------------------------------------------
print "\n";
print "  El producto por su cuenta\n";
print '  ' . str_repeat('=', 66) . "\n";

$campoConfig = \Drupal::config('field.field.tec_product.tec_product.' . CAMPO);
printf("    %-7s %-16s %-28s %s\n", $campoConfig->get('required') ? 'VIVO' : 'DUDOSO',
  'el campo', 'en la ficha del producto',
  $campoConfig->get('required') ? 'OBLIGATORIO: sin el no se puede crear un producto' : 'no es obligatorio');
$vivos++;

foreach ($almacenConfig->listAll('core.entity_form_display.tec_product.') as $nombrePantalla) {
  $pantalla = \Drupal::config($nombrePantalla)->getRawData();
  if (!isset($pantalla['content'][CAMPO])) {
    continue;
  }
  printf("    %-7s %-16s %-28s %s\n", 'VIVO', 'formulario',
    $pantalla['mode'] ?? '?', $pantalla['content'][CAMPO]['type'] ?? '?');
  $vivos++;
}

foreach ($almacenConfig->listAll('core.entity_view_display.tec_product.') as $nombrePantalla) {
  $pantalla = \Drupal::config($nombrePantalla)->getRawData();
  $enContenido = isset($pantalla['content'][CAMPO]);
  $enMaqueta = str_contains($aTexto($pantalla['third_party_settings']['layout_builder'] ?? []), CAMPO);
  if (!$enContenido && !$enMaqueta) {
    continue;
  }
  printf("    %-7s %-16s %-28s %s\n", 'VIVO', 'ficha',
    $pantalla['mode'] ?? '?', $enMaqueta ? 'dentro de la maqueta' : 'como campo suelto');
  $vivos++;
}

// ---------------------------------------------------------------------------
// 3. Las ECA.
// ---------------------------------------------------------------------------
print "\n";
print "  Las ECA que lo tocan\n";
print '  ' . str_repeat('=', 66) . "\n";

$ningunaEca = TRUE;
foreach ($almacenConfig->listAll('eca.eca.') as $nombreEca) {
  $eca = \Drupal::config($nombreEca)->getRawData();
  $texto = $aTexto($eca);
  if (!str_contains($texto, CAMPO)) {
    continue;
  }
  if (!str_contains($texto, 'field.storage.tec_product.' . CAMPO) && !str_contains($texto, 'field.field.tec_product.')) {
    continue;
  }
  $ningunaEca = FALSE;
  $encendida = (bool) ($eca['status'] ?? FALSE);
  printf("    %-7s %-16s %s\n", $encendida ? 'VIVO' : 'MUERTO',
    $eca['id'] ?? '?', mb_substr((string) ($eca['label'] ?? ''), 0, 50));
  $encendida ? $vivos++ : $muertos++;
}
if ($ningunaEca) {
  print "    ninguna\n";
}

// ---------------------------------------------------------------------------
// 4. Los enlaces escritos a mano.
// ---------------------------------------------------------------------------
print "\n";
print "  Enlaces que rellenan el cliente desde la direccion\n";
print '  ' . str_repeat('=', 66) . "\n";
print "  (son texto suelto: el dia que el campo no exista dejan de hacer nada y\n";
print "   nadie se enterara, porque un parametro que sobra no da ningun error)\n\n";

$ningunEnlace = TRUE;
foreach ($todo as $nombre => $texto) {
  $aMano = str_contains($texto, 'edit[' . CAMPO . ']');
  // El campo del material tambien se rellena con `target_id`, y ese no se toca.
  $porElModulo = str_contains($texto, 'query:target_id') && str_contains($nombre, 'tec_product');
  if ($aMano || $porElModulo) {
    $ningunEnlace = FALSE;
    printf("    %-58s %s\n", $nombre, $aMano ? 'escrito a mano' : 'lo rellena el modulo epp');
  }
}
if ($ningunEnlace) {
  print "    ninguno\n";
}

// ---------------------------------------------------------------------------
// 5. La prueba de lo que se encontro en los datos.
// ---------------------------------------------------------------------------
print "\n";
print "  Bloques metidos en maquetas, que no son configuracion\n";
print '  ' . str_repeat('=', 66) . "\n";
printf("  (%d tablas de maqueta miradas; sin esto, el bloque de /p salia muerto)\n\n", $columnasMiradas);
foreach ($enLosDatos as $plugin => $donde) {
  printf("    %-46s %s\n", $plugin, $donde);
}

print "\n=====================================================================\n";
printf("  %d lectores vivos, %d muertos.\n", $vivos, $muertos);
print "=====================================================================\n\n";
