<?php

/**
 * @file
 * Busca si la pantalla maestra de una vista se dibuja en algun sitio de verdad.
 *
 *   php vendor/bin/drush scr scripts/quien-dibuja-el-display-maestro.php
 *   php vendor/bin/drush scr scripts/quien-dibuja-el-display-maestro.php -- tec_order_material_calculation_summary
 *
 * La pantalla `default` de una vista es la plantilla de la que heredan las
 * demas. Views no le da ni direccion ni bloque, asi que en principio no la ve
 * nadie mas que quien abra la vista para editarla. Pero "en principio" no vale
 * como respuesta: si alguien la ha incrustado a mano, entonces lo que parece un
 * arreglo cosmetico es un fallo que esta viendo la fabrica todos los dias, y eso
 * cambia la prioridad del cabo y como hay que tratarlo.
 *
 * Se mira en los cinco sitios por los que una pantalla puede colarse:
 *
 *   1. Las direcciones propias de las pantallas de la vista, para descartar que
 *      la maestra tenga una.
 *   2. Los bloques colocados en el tema, que es como se dibuja un display de
 *      bloque. Solo los display de bloque generan plugin, y aqui se comprueba.
 *   3. Toda la configuracion activa, buscando el nombre de la vista. Asi salen
 *      las pestanas de quicktabs, otras vistas que la incrusten como area, los
 *      campos de referencia a vista y las pantallas de ver una entidad.
 *   4. Todo el codigo del sitio, buscando las funciones con las que se incrusta
 *      una vista a mano (`views_embed_view`, `drupal_view`, `Views::getView`).
 *      Es la unica via por la que la maestra puede acabar dibujandose.
 *   5. El contenido guardado en la base de datos, por si el nombre de la vista
 *      esta escrito dentro del cuerpo de un nodo o de un bloque de contenido.
 *
 * No escribe nada.
 */

// Drush deja `$extra` definido y vacio cuando no se le pasa nada detras de los
// dos guiones, asi que no vale un `??`: hay que mirar si trae algo dentro.
$vistas = $extra ?? [];
if (!$vistas) {
  $vistas = ['tec_order_material_calculation_summary'];
}

foreach ($vistas as $nombreVista) {
  echo "\n";
  echo str_repeat('=', 96) . "\n";
  echo " $nombreVista\n";
  echo str_repeat('=', 96) . "\n";

  $config = \Drupal::config("views.view.$nombreVista");
  if ($config->isNew()) {
    echo " No existe esa vista.\n";
    continue;
  }

  lasPantallas($config);
  losBloquesColocados($nombreVista);
  laConfiguracion($nombreVista);
  elCodigo($nombreVista);
  elContenido($nombreVista);
}

echo "\n";

/**
 * Que pantallas tiene la vista y que puede publicar cada una.
 */
function lasPantallas($config): void {
  echo "\n 1. Pantallas de la vista\n\n";
  printf("    %-12s %-16s %-28s %s\n", 'pantalla', 'plantilla', 'direccion', 'que publica');
  printf("    %s\n", str_repeat('-', 88));

  foreach ($config->get('display') ?? [] as $id => $display) {
    $plantilla = $display['display_plugin'] ?? '?';
    $ruta = $display['display_options']['path'] ?? '';
    // Views solo crea ruta para las pantallas de pagina y de descarga, y solo
    // crea plugin de bloque para las de bloque. La maestra no entra en ninguno
    // de los dos grupos, y eso no es una opcion que se pueda cambiar: es como
    // esta hecho Views.
    $publica = match ($plantilla) {
      'page' => $ruta === '' ? 'nada, le falta la direccion' : 'una ruta',
      'feed', 'data_export' => $ruta === '' ? 'nada, le falta la direccion' : 'una descarga',
      'block' => 'un bloque colocable',
      'attachment' => 'se pega a otra pantalla de la misma vista',
      'default' => 'NADA por si misma: es la plantilla de la que heredan las demas',
      default => 'sin saber',
    };
    printf("    %-12s %-16s %-28s %s\n", $id, $plantilla, $ruta === '' ? '(ninguna)' : $ruta, $publica);
  }
}

/**
 * Los bloques de esta vista que estan colocados en alguna region del tema.
 */
function losBloquesColocados(string $nombreVista): void {
  echo "\n 2. Bloques colocados en el tema\n\n";
  $encontrados = 0;
  $factory = \Drupal::configFactory();
  foreach ($factory->listAll('block.block.') as $nombre) {
    $bloque = $factory->get($nombre);
    $plugin = (string) $bloque->get('plugin');
    if (!str_contains($plugin, 'views_block:' . $nombreVista . '-')) {
      continue;
    }
    $encontrados++;
    printf(
      "    %-46s %s   region %s, tema %s, %s\n",
      $nombre,
      $plugin,
      $bloque->get('region') ?: '(sin region)',
      $bloque->get('theme'),
      $bloque->get('status') ? 'encendido' : 'apagado'
    );
  }
  if ($encontrados === 0) {
    echo "    Ninguno.\n";
  }
}

/**
 * Donde aparece el nombre de la vista en la configuracion activa.
 */
function laConfiguracion(string $nombreVista): void {
  echo "\n 3. Configuracion activa que nombra la vista\n\n";
  $almacen = \Drupal::service('config.storage');
  $encontrados = 0;

  foreach ($almacen->listAll() as $nombre) {
    // La propia vista se nombra a si misma en las dependencias; no cuenta.
    if ($nombre === "views.view.$nombreVista") {
      continue;
    }
    $datos = $almacen->read($nombre) ?: [];
    $donde = buscaEnElArbol($datos, $nombreVista);
    if (!$donde) {
      continue;
    }
    $encontrados++;
    printf("    %s\n", $nombre);
    foreach ($donde as $ruta => $valor) {
      printf("        %-56s %s\n", $ruta, $valor);
      // Si al lado de la vista hay una clave que diga la pantalla, se ensena:
      // es lo que distingue "usa el bloque" de "usa la maestra".
      foreach (laPantallaQueDiceAlLado($datos, $ruta) as $clave => $cual) {
        printf("        %-56s %s\n", '  ' . $clave, $cual);
      }
    }
  }
  if ($encontrados === 0) {
    echo "    Nada. Ninguna otra configuracion la nombra.\n";
  }
}

/**
 * Busca el nombre de la vista dentro de un arbol de configuracion.
 *
 * @return array
 *   Ruta de claves separada por barras => valor donde aparece.
 */
function buscaEnElArbol($datos, string $aguja, string $camino = ''): array {
  $encontrado = [];
  if (is_array($datos)) {
    foreach ($datos as $clave => $valor) {
      $encontrado += buscaEnElArbol($valor, $aguja, $camino === '' ? (string) $clave : $camino . '/' . $clave);
    }
    return $encontrado;
  }
  if (is_string($datos) && str_contains($datos, $aguja)) {
    $encontrado[$camino] = strlen($datos) > 90 ? substr($datos, 0, 90) . '...' : $datos;
  }
  return $encontrado;
}

/**
 * Busca, junto a donde aparece la vista, la clave que dice que pantalla se usa.
 *
 * Quicktabs guarda `vid` y `display` como hermanas, y los campos de referencia
 * a vista guardan `display_id`. Sin esto se sabe que algo usa la vista pero no
 * cual de sus pantallas, que es justo la pregunta.
 */
function laPantallaQueDiceAlLado(array $datos, string $ruta): array {
  $trozos = explode('/', $ruta);
  array_pop($trozos);
  $nodo = $datos;
  foreach ($trozos as $trozo) {
    if (!is_array($nodo) || !array_key_exists($trozo, $nodo)) {
      return [];
    }
    $nodo = $nodo[$trozo];
  }
  if (!is_array($nodo)) {
    return [];
  }
  $dicen = [];
  foreach (['display', 'display_id', 'view_display', 'target_id'] as $clave) {
    if (isset($nodo[$clave]) && is_string($nodo[$clave])) {
      $dicen[$clave] = $nodo[$clave];
    }
  }
  return $dicen;
}

/**
 * Que ficheros del sitio nombran la vista, y si la incrustan a mano.
 */
function elCodigo(string $nombreVista): void {
  echo "\n 4. Codigo que nombra la vista\n\n";
  $raiz = dirname(__DIR__);
  // Se mira todo menos vendor y core: una incrustacion a mano de una vista de
  // este ERP solo puede estar en el codigo del sitio.
  $carpetas = ['modules', 'themes', 'profiles', 'recipes', 'tec', 'sites', 'libraries'];
  $extensiones = ['php', 'module', 'inc', 'theme', 'twig', 'yml', 'js'];
  $incrustadores = ['views_embed_view', 'drupal_view', 'Views::getView', 'ViewExecutable', 'views_block'];
  // Carpetas que no llevan codigo del sitio y si llevan decenas de miles de
  // ficheros. En Windows, abrir cada uno cuesta lo suyo: sin este filtro el
  // recorrido pasa de segundos a minutos y parece que el guion se ha colgado.
  $saltar = ['node_modules', '.git', 'files', 'dist', 'build', 'vendor'];

  $encontrados = 0;
  foreach ($carpetas as $carpeta) {
    $ruta = $raiz . DIRECTORY_SEPARATOR . $carpeta;
    if (!is_dir($ruta)) {
      continue;
    }
    $mirados = 0;
    $reloj = microtime(TRUE);
    $iterador = new \RecursiveIteratorIterator(
      new \RecursiveCallbackFilterIterator(
        new \RecursiveDirectoryIterator($ruta, \FilesystemIterator::SKIP_DOTS),
        function ($fichero) use ($saltar) {
          return !$fichero->isDir() || !in_array($fichero->getFilename(), $saltar, TRUE);
        }
      )
    );
    foreach ($iterador as $fichero) {
      if (!$fichero->isFile() || !in_array(strtolower($fichero->getExtension()), $extensiones, TRUE)) {
        continue;
      }
      $mirados++;
      $texto = @file_get_contents($fichero->getPathname());
      if ($texto === FALSE || !str_contains($texto, $nombreVista)) {
        continue;
      }
      $encontrados++;
      $relativo = str_replace($raiz . DIRECTORY_SEPARATOR, '', $fichero->getPathname());
      printf("    %s\n", $relativo);
      foreach (explode("\n", $texto) as $numero => $linea) {
        if (!str_contains($linea, $nombreVista)) {
          continue;
        }
        $recortada = trim(preg_replace('/\s+/', ' ', $linea));
        $marca = '';
        foreach ($incrustadores as $incrustador) {
          if (str_contains($texto, $incrustador)) {
            $marca = '   <-- el fichero usa ' . $incrustador;
            break;
          }
        }
        printf("        linea %-6d %s%s\n", $numero + 1, substr($recortada, 0, 100), $marca);
      }
    }
    // Se dice cuantos ficheros se han mirado en cada carpeta: un cero aqui
    // significa que la carpeta se ha saltado sin avisar, y entonces el "no
    // aparece en ningun sitio" del final no valdria nada.
    printf("    (%s: %d ficheros mirados en %d s)\n", $carpeta, $mirados, round(microtime(TRUE) - $reloj));
    flush();
  }
  if ($encontrados === 0) {
    echo "    Ningun fichero de codigo la nombra.\n";
  }
}

/**
 * Si el nombre de la vista esta escrito dentro del contenido guardado.
 *
 * Es la via mas escondida: un nodo con la vista incrustada en el cuerpo no
 * aparece ni en la configuracion ni en el codigo. Se recorren las columnas de
 * texto largo de la base de datos, saltando las que solo guardan rastros
 * (registro, cache, cola, traducciones).
 */
function elContenido(string $nombreVista): void {
  echo "\n 5. Contenido guardado que nombra la vista\n\n";
  $conexion = \Drupal::database();
  $noMirar = [
    'watchdog', 'sessions', 'semaphore', 'queue', 'flood', 'config',
    'locales_source', 'locales_target', 'locale_file',
    'key_value_expire', 'search_index', 'search_dataset', 'search_total',
  ];
  // Estas tres tablas son almacenes de configuracion, no contenido: guardan una
  // copia de la propia vista. Aparecen siempre y no significan nada, pero se
  // dicen igual y con su explicacion, porque callarlas seria esconder una parte
  // de la respuesta.
  $sonCopiasDeLaConfig = ['config_export', 'config_import', 'config_snapshot'];

  $columnas = $conexion->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND (DATA_TYPE IN ('text', 'mediumtext', 'longtext')
           OR (DATA_TYPE = 'varchar' AND CHARACTER_MAXIMUM_LENGTH >= 128))
  ")->fetchAll();

  $encontrados = 0;
  foreach ($columnas as $columna) {
    $tabla = $columna->TABLE_NAME;
    if (in_array($tabla, $noMirar, TRUE) || str_starts_with($tabla, 'cache')) {
      continue;
    }
    try {
      $cuantos = $conexion->query(
        'SELECT COUNT(*) FROM `' . $tabla . '` WHERE `' . $columna->COLUMN_NAME . '` LIKE :aguja',
        [':aguja' => '%' . $nombreVista . '%']
      )->fetchField();
    }
    catch (\Exception $e) {
      continue;
    }
    if ($cuantos > 0) {
      $encontrados++;
      printf(
        "    %s.%s   %d filas%s\n",
        $tabla,
        $columna->COLUMN_NAME,
        $cuantos,
        in_array($tabla, $sonCopiasDeLaConfig, TRUE) ? '   (copia de la configuracion, no es contenido)' : ''
      );
      // El valor en crudo, que es lo unico que permite decidir si eso dibuja la
      // vista o solo la nombra.
      $muestras = $conexion->queryRange(
        'SELECT `' . $columna->COLUMN_NAME . '` FROM `' . $tabla . '` WHERE `' . $columna->COLUMN_NAME . '` LIKE :aguja',
        0,
        2,
        [':aguja' => '%' . $nombreVista . '%']
      )->fetchCol();
      foreach ($muestras as $muestra) {
        printf("        %s\n", substr(preg_replace('/\s+/', ' ', (string) $muestra), 0, 140));
      }
    }
  }
  if ($encontrados === 0) {
    echo "    Nada. No esta escrita dentro de ningun contenido.\n";
  }
}
