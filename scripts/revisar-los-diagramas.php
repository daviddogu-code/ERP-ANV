<?php

/**
 * Revisa que los diagramas de ECA esten enteros y cuadren con el ejecutable.
 *
 * Cada proceso de ECA vive en dos ficheros: eca.eca.PROCESO.yml, que es lo que
 * Drupal ejecuta, y eca.model.PROCESO.yml, que guarda el diagrama en XML y es
 * lo que ve el modelador grafico. Si los dos se separan, la proxima vez que
 * alguien abra el diagrama y guarde, el diagrama pisa el ejecutable y resucita
 * lo que se hubiera quitado a mano.
 *
 * Comprueba cuatro cosas por proceso:
 *   1. Que el XML del diagrama sea valido.
 *   2. Que ninguna flecha salga de o apunte a un elemento que no existe.
 *   3. Que las listas de entradas y salidas de cada elemento nombren flechas
 *      que existan.
 *   4. Que ningun dibujo se refiera a un elemento borrado.
 *
 * Y una quinta cosa cruzando los dos ficheros: que todo lo que el ejecutable
 * nombra este en el diagrama y al reves.
 *
 * Uso: php scripts/revisar-los-diagramas.php [proceso]
 * Sin argumento revisa los treinta y seis. Con argumento, solo ese.
 */

$raiz = dirname(__DIR__);
$carpeta = $raiz . '/config/sync';
$solo = $argv[1] ?? NULL;

$ficheros = glob($carpeta . '/eca.model.*.yml');
sort($ficheros);

$problemas = 0;
$revisados = 0;

foreach ($ficheros as $fichero) {
  $nombre = basename($fichero, '.yml');
  $proceso = substr($nombre, strlen('eca.model.'));
  if ($solo !== NULL && $proceso !== $solo && $proceso !== 'process_' . $solo) {
    continue;
  }
  $revisados++;
  $fallos = revisar($carpeta, $proceso);
  if ($fallos) {
    $problemas += count($fallos);
    echo "\n  $proceso\n";
    foreach ($fallos as $fallo) {
      echo "    - $fallo\n";
    }
  }
}

echo "\n";
echo "Procesos revisados: $revisados\n";
if ($problemas === 0) {
  echo "Diagramas enteros y cuadrados con el ejecutable.\n";
  exit(0);
}
echo "Problemas encontrados: $problemas\n";
exit(1);

/**
 * Revisa un proceso y devuelve la lista de problemas en texto llano.
 */
function revisar(string $carpeta, string $proceso): array {
  $fallos = [];

  $ficheroModelo = "$carpeta/eca.model.$proceso.yml";
  $ficheroEca = "$carpeta/eca.eca.$proceso.yml";

  $xml = sacarElXml($ficheroModelo);
  if ($xml === NULL) {
    return ['No se ha encontrado el XML del diagrama dentro del fichero.'];
  }

  $documento = new DOMDocument();
  libxml_use_internal_errors(TRUE);
  if (!$documento->loadXML($xml)) {
    foreach (libxml_get_errors() as $error) {
      $fallos[] = 'XML invalido: ' . trim($error->message);
    }
    libxml_clear_errors();
    return $fallos;
  }
  libxml_clear_errors();

  // Todo lo que tiene identidad propia dentro del proceso.
  $existen = [];
  $flechas = [];
  foreach ($documento->getElementsByTagName('*') as $nodo) {
    $etiqueta = $nodo->localName;
    $id = $nodo->getAttribute('id');
    if ($id === '' || str_starts_with($etiqueta, 'BPMN')) {
      continue;
    }
    $existen[$id] = $etiqueta;
    if ($etiqueta === 'sequenceFlow') {
      $flechas[$id] = [
        'de' => $nodo->getAttribute('sourceRef'),
        'a' => $nodo->getAttribute('targetRef'),
      ];
    }
  }

  // 2. Flechas que salen de la nada o van a la nada.
  foreach ($flechas as $id => $extremos) {
    foreach (['de' => 'sale de', 'a' => 'apunta a'] as $lado => $verbo) {
      $destino = $extremos[$lado];
      if ($destino !== '' && !isset($existen[$destino])) {
        $fallos[] = "La flecha $id $verbo $destino, que no existe.";
      }
    }
  }

  // 3. Entradas y salidas que nombran flechas inexistentes, y flechas que
  // existen pero que el elemento del otro extremo no reconoce.
  foreach (['incoming' => 'entrada', 'outgoing' => 'salida'] as $etiqueta => $palabra) {
    foreach ($documento->getElementsByTagName('*') as $nodo) {
      if ($nodo->localName !== $etiqueta) {
        continue;
      }
      $flecha = trim($nodo->textContent);
      $duenyo = $nodo->parentNode->getAttribute('id');
      if (!isset($flechas[$flecha])) {
        $fallos[] = "$duenyo declara como $palabra la flecha $flecha, que no existe.";
        continue;
      }
      $extremo = $etiqueta === 'incoming' ? 'a' : 'de';
      if ($flechas[$flecha][$extremo] !== $duenyo) {
        $fallos[] = "$duenyo declara como $palabra la flecha $flecha, pero esa flecha no le toca.";
      }
    }
  }

  // Al reves: flechas cuyos extremos no las declaran.
  foreach ($flechas as $id => $extremos) {
    foreach (['de' => 'outgoing', 'a' => 'incoming'] as $lado => $etiqueta) {
      $elemento = $extremos[$lado];
      if ($elemento === '' || !isset($existen[$elemento])) {
        continue;
      }
      $nodo = buscarPorId($documento, $elemento);
      $declaradas = [];
      foreach ($nodo->childNodes as $hijo) {
        if ($hijo instanceof DOMElement && $hijo->localName === $etiqueta) {
          $declaradas[] = trim($hijo->textContent);
        }
      }
      if (!in_array($id, $declaradas, TRUE)) {
        $palabra = $etiqueta === 'incoming' ? 'entrada' : 'salida';
        $fallos[] = "$elemento no declara como $palabra la flecha $id, que le toca.";
      }
    }
  }

  // 4. Dibujos de elementos que ya no existen.
  foreach ($documento->getElementsByTagName('*') as $nodo) {
    if (!in_array($nodo->localName, ['BPMNShape', 'BPMNEdge'], TRUE)) {
      continue;
    }
    $referencia = $nodo->getAttribute('bpmnElement');
    if ($referencia !== '' && !isset($existen[$referencia]) && !str_starts_with($referencia, 'Process_')) {
      $fallos[] = "Hay un dibujo de $referencia, que ya no existe en el diagrama.";
    }
  }

  // 5. Cruce con el ejecutable.
  if (!is_file($ficheroEca)) {
    $fallos[] = 'No hay ejecutable para este diagrama.';
    return $fallos;
  }
  $eca = leerYaml($ficheroEca);
  $enElEjecutable = [];
  foreach (['events', 'conditions', 'gateways', 'actions'] as $bloque) {
    foreach (array_keys($eca[$bloque] ?? []) as $id) {
      $enElEjecutable[$id] = $bloque;
    }
  }
  foreach ($enElEjecutable as $id => $bloque) {
    if (!isset($existen[$id])) {
      $fallos[] = "El ejecutable tiene $id en $bloque, pero no esta en el diagrama.";
    }
  }
  // Del diagrama al ejecutable solo miramos lo que ECA ejecuta de verdad.
  $ejecutables = ['startEvent', 'task', 'exclusiveGateway', 'parallelGateway', 'sequenceFlow'];
  foreach ($existen as $id => $etiqueta) {
    if (!in_array($etiqueta, $ejecutables, TRUE)) {
      continue;
    }
    // Las flechas sin condicion no aparecen en el ejecutable, es normal.
    if ($etiqueta === 'sequenceFlow' && !isset($enElEjecutable[$id])) {
      continue;
    }
    if (!isset($enElEjecutable[$id])) {
      $fallos[] = "El diagrama tiene $id ($etiqueta), pero el ejecutable no lo conoce.";
    }
  }

  // Los sucesores del ejecutable tienen que existir.
  foreach (['events', 'gateways', 'actions'] as $bloque) {
    foreach ($eca[$bloque] ?? [] as $id => $definicion) {
      foreach ($definicion['successors'] ?? [] as $sucesor) {
        $siguiente = $sucesor['id'] ?? '';
        if ($siguiente !== '' && !isset($enElEjecutable[$siguiente])) {
          $fallos[] = "$id lleva a $siguiente, que el ejecutable no tiene.";
        }
        $condicion = $sucesor['condition'] ?? '';
        if ($condicion !== '' && !isset($eca['conditions'][$condicion])) {
          $fallos[] = "$id usa la condicion $condicion, que el ejecutable no tiene.";
        }
      }
    }
  }

  return $fallos;
}

/**
 * Busca un nodo por su atributo id.
 */
function buscarPorId(DOMDocument $documento, string $id): ?DOMElement {
  foreach ($documento->getElementsByTagName('*') as $nodo) {
    if ($nodo->getAttribute('id') === $id) {
      return $nodo;
    }
  }
  return NULL;
}

/**
 * Saca el XML del diagrama de dentro del fichero de configuracion.
 *
 * El XML viene como bloque literal de YAML detras de la clave modeldata, con
 * dos espacios de sangria que hay que quitar para que el XML sea valido.
 */
function sacarElXml(string $fichero): ?string {
  $lineas = file($fichero, FILE_IGNORE_NEW_LINES);
  $dentro = FALSE;
  $trozos = [];
  foreach ($lineas as $linea) {
    if (!$dentro) {
      if (preg_match('/^modeldata:\s*\|/', $linea)) {
        $dentro = TRUE;
      }
      continue;
    }
    // El bloque acaba en la primera linea sin sangria.
    if ($linea !== '' && !str_starts_with($linea, ' ')) {
      break;
    }
    $trozos[] = preg_replace('/^ {2}/', '', $linea);
  }
  return $trozos ? implode("\n", $trozos) : NULL;
}

/**
 * Lee un YAML de configuracion sin depender de Drupal.
 */
function leerYaml(string $fichero): array {
  if (function_exists('yaml_parse_file')) {
    $datos = yaml_parse_file($fichero);
    if (is_array($datos)) {
      return $datos;
    }
  }
  $autoload = dirname(__DIR__) . '/vendor/autoload.php';
  require_once $autoload;
  return \Symfony\Component\Yaml\Yaml::parseFile($fichero) ?: [];
}
