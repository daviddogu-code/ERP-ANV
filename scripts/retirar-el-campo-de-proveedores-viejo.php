<?php

/**
 * @file
 * Retira field_tec_suppliers, el campo de proveedor viejo de los materiales.
 *
 *   php vendor\bin\drush.php scr scripts/retirar-el-campo-de-proveedores-viejo.php
 *   php vendor\bin\drush.php scr scripts/retirar-el-campo-de-proveedores-viejo.php -- --de-verdad
 *
 * Sin --de-verdad no cambia nada: cuenta lo que haria, paso por paso.
 *
 * Los materiales tienen dos campos de proveedor. field_tec_vendor es el que se
 * usa: tiene datos, es obligatorio y es el que lista la pestana del proveedor.
 * field_tec_suppliers es el viejo, admite varios y su tabla no tiene ni una
 * fila, asi que al retirarlo no se pierde ningun dato. Lo unico que hacia falta
 * de el, el relleno automatico de epp, ya se paso a field_tec_vendor en
 * scripts/arreglar-el-boton-de-anadir-material.php.
 *
 * El orden importa y es este, porque al reves Drupal decide solo:
 *
 *   1. Primero la vista tec_inventory, que es la pantalla mas usada del ERP y
 *      la que lo tiene mas cosido: columnas en cuatro pantallas, una relacion en
 *      dos, las entradas de las columnas de la tabla y una columna 'id' colgada
 *      de la relacion.
 *   2. Despues las pantallas de ver y editar el material.
 *   3. Y solo entonces el campo y su almacen.
 *
 * Si se borrase el campo primero, Drupal limpiaria las vistas por su cuenta con
 * onDependencyRemoval(), y cuando no sabe quitar un manejador limpiamente apaga
 * la pantalla entera o la vista. Quitandolo a mano antes, cuando se borra el
 * campo ya no queda nada que dependa de el.
 *
 * Va con red por partida doble: no busca solo donde ya sabemos que esta, sino
 * que repasa TODAS las vistas y TODAS las pantallas buscando el campo, y despues
 * de armar la vista nueva vuelve a repasarla; si algo la sigue nombrando, no
 * guarda nada. La vista tiene mas de cinco mil lineas y no se toca a mano.
 *
 * Antes de lanzarlo con --de-verdad hay una copia en
 * backups/views.view.tec_inventory.backup-2026-08-15.yml
 */

const CAMPO = 'field_tec_suppliers';
const TABLA = 'taxonomy_term__field_tec_suppliers';
const ALMACEN = 'field.storage.taxonomy_term.field_tec_suppliers';
const CAMPO_ENTERO = 'taxonomy_term.tec_inventory.field_tec_suppliers';

// La columna que cuelga de la relacion. Se va con ella: esta excluida, asi que
// no se dibuja nunca, y lo unico que la leia era la marca {{ id }} de la
// cabecera de block_2, que nunca funciono y que ya se cambio por la marca del
// argumento. Ademas colgaba de una relacion con una tabla vacia, asi que ni
// arreglando la marca habria devuelto nada.
const COLUMNA_COLGADA = ['tec_inventory' => ['block_2' => ['id']]];

$deVerdad = FALSE;
foreach ((array) ($extra ?? []) as $argumento) {
  if (in_array($argumento, ['--de-verdad', 'de-verdad'], TRUE)) {
    $deVerdad = TRUE;
  }
}

$titulo = function (string $texto) {
  echo "\n" . $texto . "\n" . str_repeat('-', 78) . "\n";
};

$manosDeVista = ['fields' => 'columna', 'filters' => 'filtro', 'sorts' => 'orden', 'arguments' => 'argumento', 'relationships' => 'relacion'];

echo "\n" . str_repeat('=', 78) . "\n";
echo $deVerdad
  ? " DE VERDAD: se quita y se borra\n"
  : " ENSAYO: solo se cuenta lo que haria. Anade -- --de-verdad para hacerlo\n";
echo str_repeat('=', 78) . "\n";

$etm = \Drupal::entityTypeManager();

// ---------------------------------------------------------------------------
// Lo primero: que el campo siga sin datos. Si alguien ha metido filas mientras
// se preparaba esto, se para. Borrar el campo borraria los datos.
// ---------------------------------------------------------------------------
$titulo('Lo que guarda el campo');
$bd = \Drupal::database();
if ($bd->schema()->tableExists(TABLA)) {
  $filas = (int) $bd->select(TABLA, 't')->countQuery()->execute()->fetchField();
  printf("  %s: %d filas\n", TABLA, $filas);
  if ($filas > 0) {
    echo "\n  NO SE HACE NADA: el campo tiene datos. Al borrarlo se perderian.\n";
    echo "  Habria que decidir primero que se hace con esas filas.\n\n";
    return;
  }
}
else {
  printf("  %s: la tabla ya no existe\n", TABLA);
}

// Y que el relleno automatico ya este en el campo bueno, porque al borrar este
// campo se va con el.
$viejo = $etm->getStorage('field_config')->load(CAMPO_ENTERO);
$bueno = $etm->getStorage('field_config')->load('taxonomy_term.tec_inventory.field_tec_vendor');
$rellenoViejo = $viejo ? (string) $viejo->getThirdPartySetting('epp', 'value', '') : '';
$rellenoBueno = $bueno ? (string) $bueno->getThirdPartySetting('epp', 'value', '') : '';
printf("  relleno de epp en el campo viejo:  %s\n", $rellenoViejo !== '' ? $rellenoViejo : 'ninguno');
printf("  relleno de epp en el campo bueno:  %s\n", $rellenoBueno !== '' ? $rellenoBueno : 'ninguno');
if ($rellenoViejo !== '' && $rellenoBueno === '') {
  echo "\n  NO SE HACE NADA: el relleno automatico sigue en el campo viejo y se\n";
  echo "  perderia. Lanza antes scripts/arreglar-el-boton-de-anadir-material.php\n\n";
  return;
}

// ---------------------------------------------------------------------------
// Quien lo nombra en TODA la configuracion activa, no solo donde ya sabemos que
// esta. Vale como foto del antes y, volviendo a lanzar el guion en ensayo cuando
// ya se ha hecho, como comprobacion de que no queda ni un cabo suelto.
// ---------------------------------------------------------------------------
$titulo('Quien lo nombra en toda la configuracion activa');
$activa = \Drupal::service('config.storage');
$loNombran = [];
foreach ($activa->listAll() as $nombre) {
  $texto = json_encode($activa->read($nombre));
  if ($texto !== FALSE && str_contains($texto, CAMPO)) {
    $loNombran[] = $nombre;
  }
}
if (!$loNombran) {
  echo "  nadie: no queda ni un cabo suelto\n";
}
foreach ($loNombran as $nombre) {
  echo '  ' . $nombre . "\n";
}

// ---------------------------------------------------------------------------
// Paso 1: la cirugia de las vistas.
//
// No se apunta a mano donde esta: se repasan todas las vistas y todas sus
// pantallas. Asi el guion tambien sirve de comprobacion, y si alguien anadio el
// campo en otro sitio tambien lo pilla.
// ---------------------------------------------------------------------------
$titulo('Paso 1: las vistas');

$almacenVistas = $etm->getStorage('view');
$vistasTocadas = [];

foreach ($almacenVistas->loadMultiple() as $vista) {
  $pantallas = $vista->get('display');
  $quitado = [];

  foreach ($pantallas as $idPantalla => $pantalla) {
    $opciones = &$pantallas[$idPantalla]['display_options'];

    foreach ($manosDeVista as $donde => $comoSeLlama) {
      foreach ($opciones[$donde] ?? [] as $clave => $mano) {
        $esElCampo = ($mano['field'] ?? NULL) === CAMPO
          || str_starts_with((string) ($mano['table'] ?? ''), TABLA);
        $cuelgaDeEl = str_starts_with((string) ($mano['relationship'] ?? 'none'), CAMPO);

        if ($esElCampo) {
          unset($opciones[$donde][$clave]);
          $quitado[] = sprintf('%-10s %-12s %s', $idPantalla, $comoSeLlama, $clave);
        }
        elseif ($cuelgaDeEl) {
          // Cuelga de la relacion que se va. Si esta en la lista de columnas
          // condenadas se quita; si no, se para todo, porque dejarla colgando
          // rompe la consulta.
          $condenada = in_array($clave, COLUMNA_COLGADA[$vista->id()][$idPantalla] ?? [], TRUE);
          if (!$condenada) {
            printf("\n  NO SE HACE NADA: en %s/%s la %s '%s' cuelga de la relacion\n", $vista->id(), $idPantalla, $comoSeLlama, $clave);
            echo "  y no esta decidido que hacer con ella. Decidelo antes de seguir.\n\n";
            return;
          }
          unset($opciones[$donde][$clave]);
          $quitado[] = sprintf('%-10s %-12s %s   (colgaba de la relacion)', $idPantalla, $comoSeLlama, $clave);
        }
      }
    }

    // Las columnas de la tabla se guardan aparte de las columnas de la vista.
    // Ojo: en los estilos de rejilla 'columns' es un numero, no una lista de
    // columnas, y ahi no hay nada que quitar.
    foreach (['columns', 'info'] as $parte) {
      $lista = $opciones['style']['options'][$parte] ?? [];
      if (!is_array($lista)) {
        continue;
      }
      if (isset($lista[CAMPO])) {
        unset($opciones['style']['options'][$parte][CAMPO]);
        $quitado[] = sprintf('%-10s %-12s %s', $idPantalla, 'tabla (' . $parte . ')', CAMPO);
      }
      // Y si alguna otra columna se dibujaba dentro de la que se va, se queda
      // sin sitio. No es el caso aqui, pero conviene enterarse si pasa.
      foreach ($lista as $columna => $dentroDe) {
        if (is_string($dentroDe) && $dentroDe === CAMPO && $columna !== CAMPO) {
          printf("\n  NO SE HACE NADA: la columna '%s' de %s/%s se dibuja dentro de %s\n", $columna, $vista->id(), $idPantalla, CAMPO);
          echo "  y se quedaria sin sitio. Decidelo antes de seguir.\n\n";
          return;
        }
      }
    }
    unset($opciones);
  }

  if (!$quitado) {
    continue;
  }

  // La red: se repasa la vista ya armada. Si algo la sigue nombrando, se para.
  $sobras = buscarElCampo($pantallas);
  if ($sobras) {
    printf("\n  NO SE HACE NADA en %s: despues de quitarlo sigue nombrado en:\n", $vista->id());
    foreach ($sobras as $sobra) {
      echo '    ' . $sobra . "\n";
    }
    echo "\n";
    return;
  }

  printf("\n  vista %s\n", $vista->id());
  foreach ($quitado as $linea) {
    echo '    ' . $linea . "\n";
  }
  $vistasTocadas[$vista->id()] = $pantallas;
}

if (!$vistasTocadas) {
  echo "  ninguna vista lo nombra ya\n";
}

// ---------------------------------------------------------------------------
// Paso 2: las pantallas de ver y editar el material.
// ---------------------------------------------------------------------------
$titulo('Paso 2: las pantallas del material');
$pantallasTocadas = [];
foreach (['entity_view_display' => 'ver', 'entity_form_display' => 'editar'] as $tipo => $comoSeLlama) {
  foreach ($etm->getStorage($tipo)->loadMultiple() as $pantalla) {
    $contenido = $pantalla->get('content') ?? [];
    $ocultos = $pantalla->get('hidden') ?? [];
    if (!isset($contenido[CAMPO]) && !isset($ocultos[CAMPO])) {
      continue;
    }
    printf(
      "  %-8s %-46s %s\n",
      $comoSeLlama,
      $pantalla->id(),
      isset($contenido[CAMPO]) ? 'se ensenaba en la posicion ' . ($contenido[CAMPO]['weight'] ?? '?') : 'estaba escondido'
    );
    $pantallasTocadas[] = [$pantalla, $tipo];
  }
}
if (!$pantallasTocadas) {
  echo "  ninguna lo nombra ya\n";
}

// ---------------------------------------------------------------------------
// Paso 3: el campo y su almacen.
// ---------------------------------------------------------------------------
$titulo('Paso 3: el campo y su almacen');
$almacen = $etm->getStorage('field_storage_config')->load('taxonomy_term.' . CAMPO);
printf("  field_config          %s\n", $viejo ? CAMPO_ENTERO : 'ya no existe');
printf("  field_storage_config  %s\n", $almacen ? 'taxonomy_term.' . CAMPO : 'ya no existe');
if ($almacen) {
  $enQueSitios = $almacen->getBundles();
  printf("  lo usan estos paquetes: %s\n", $enQueSitios ? implode(', ', $enQueSitios) : 'ninguno');
  if (count($enQueSitios) > 1) {
    echo "\n  OJO: el almacen lo usa mas de un paquete. Solo se borraria el campo,\n";
    echo "  no el almacen. Revisalo.\n";
  }
}

if (!$deVerdad) {
  echo "\n  Esto era un ensayo. Para hacerlo:\n";
  echo "  php vendor\\bin\\drush.php scr scripts/retirar-el-campo-de-proveedores-viejo.php -- --de-verdad\n\n";
  return;
}

// ---------------------------------------------------------------------------
// A hacerlo, en el orden que importa.
// ---------------------------------------------------------------------------
$titulo('Guardando, en orden');

foreach ($vistasTocadas as $idVista => $pantallas) {
  $vista = $almacenVistas->load($idVista);
  $vista->set('display', $pantallas);
  $vista->save();
  printf("  1. views.view.%s guardada\n", $idVista);
}

foreach ($pantallasTocadas as [$pantalla, $tipo]) {
  // removeComponent la saca de las columnas y la deja en la lista de
  // escondidos; para retirarla del todo hay que quitarla tambien de ahi.
  $pantalla->removeComponent(CAMPO);
  $ocultos = $pantalla->get('hidden') ?? [];
  unset($ocultos[CAMPO]);
  $pantalla->set('hidden', $ocultos);
  $pantalla->save();
  printf("  2. %s %s guardada\n", $tipo, $pantalla->id());
}

if ($viejo) {
  $viejo->delete();
  echo "  3. field.field." . CAMPO_ENTERO . " borrado\n";
}
if ($almacen = $etm->getStorage('field_storage_config')->load('taxonomy_term.' . CAMPO)) {
  $almacen->delete();
  echo "  3. field.storage.taxonomy_term." . CAMPO . " borrado\n";
}

// ---------------------------------------------------------------------------
// Paso 4: repasar los cabos. La vista guarda el campo en mas sitios de los que
// se editan a mano: en dependencies.config y en las etiquetas de cache de cada
// pantalla. Drupal los rehace al guardar, pero hay que verlo, no suponerlo.
// ---------------------------------------------------------------------------
$titulo('Paso 4: los cabos que Drupal tenia que rehacer solo');
foreach (array_keys($vistasTocadas) as $idVista) {
  $vista = $almacenVistas->loadUnchanged($idVista);
  $dependencias = $vista->get('dependencies')['config'] ?? [];
  $enDependencias = in_array(ALMACEN, $dependencias, TRUE);
  printf("  %-28s dependencies.config nombra el almacen: %s\n", $idVista, $enDependencias ? 'SI, MAL' : 'no');

  $enEtiquetas = [];
  foreach ($vista->get('display') as $idPantalla => $pantalla) {
    foreach ($pantalla['cache_metadata']['tags'] ?? [] as $etiqueta) {
      if (str_contains((string) $etiqueta, CAMPO)) {
        $enEtiquetas[] = $idPantalla;
      }
    }
  }
  printf(
    "  %-28s etiquetas de cache que lo nombran: %s\n",
    $idVista,
    $enEtiquetas ? 'SI, MAL: ' . implode(', ', array_unique($enEtiquetas)) : 'ninguna'
  );

  // Y que la vista siga siendo valida, no solo que no lo nombre.
  $problemas = [];
  foreach (array_keys($vista->get('display')) as $idPantalla) {
    $ejecutable = $vista->getExecutable();
    $ejecutable->setDisplay($idPantalla);
    foreach ($ejecutable->validate() as $porPantalla) {
      foreach ((array) $porPantalla as $queja) {
        $problemas[] = $idPantalla . ': ' . (string) $queja;
      }
    }
  }
  $problemas = array_unique($problemas);
  printf("  %-28s la vista es valida: %s\n", $idVista, $problemas ? 'NO' : 'si');
  foreach ($problemas as $problema) {
    echo '      ' . $problema . "\n";
  }
}

echo "\n  Hecho. Comprueba con:\n";
echo "  php vendor\\bin\\drush.php scr scripts/donde-se-usa-el-campo-de-proveedores.php\n";
echo "  php vendor\\bin\\drush.php scr scripts/funciona-la-pestana-de-materiales-del-proveedor.php\n";
echo "  php vendor\\bin\\drush.php scr scripts/cargan-las-paginas.php\n\n";

/**
 * Busca el campo por todos los rincones de un juego de pantallas de vista.
 *
 * Se usa como red despues de armar la vista nueva: si devuelve algo, es que la
 * cirugia se ha dejado un cabo y no hay que guardar nada.
 *
 * @param array $pantallas
 *   El array de pantallas de la vista, ya modificado.
 *
 * @return string[]
 *   Donde sigue apareciendo, en lenguaje llano.
 */
function buscarElCampo(array $pantallas): array {
  $sobras = [];
  foreach ($pantallas as $idPantalla => $pantalla) {
    $opciones = $pantalla['display_options'] ?? [];
    // Las etiquetas de cache y las dependencias no cuentan: las rehace Drupal
    // al guardar. Aqui interesan los manejadores y el estilo.
    unset($opciones['cache_metadata']);
    foreach (['fields', 'filters', 'sorts', 'arguments', 'relationships'] as $donde) {
      foreach ($opciones[$donde] ?? [] as $clave => $mano) {
        if (($mano['field'] ?? NULL) === CAMPO || str_starts_with((string) ($mano['table'] ?? ''), TABLA)) {
          $sobras[] = $idPantalla . ' ' . $donde . ' ' . $clave;
        }
        if (str_starts_with((string) ($mano['relationship'] ?? 'none'), CAMPO)) {
          $sobras[] = $idPantalla . ' ' . $donde . ' ' . $clave . ' cuelga de la relacion';
        }
      }
    }
    foreach (['columns', 'info'] as $parte) {
      $lista = $opciones['style']['options'][$parte] ?? [];
      if (is_array($lista) && isset($lista[CAMPO])) {
        $sobras[] = $idPantalla . ' estilo ' . $parte;
      }
    }
    // Y las marcas escritas a mano en textos, reescrituras y areas.
    $textos = json_encode([$opciones['header'] ?? [], $opciones['footer'] ?? [], $opciones['empty'] ?? [], $opciones['fields'] ?? []]);
    if ($textos !== FALSE && str_contains($textos, '{{ ' . CAMPO)) {
      $sobras[] = $idPantalla . ' alguna marca {{ ' . CAMPO . ' }} escrita a mano';
    }
  }
  return array_values(array_unique($sobras));
}
