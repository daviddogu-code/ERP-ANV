<?php

/**
 * Escupe la configuracion entera de una columna, tal cual esta en el fichero.
 *
 * El inventario de campos resume, y para rehacer una columna hace falta el
 * texto literal: los saltos de linea, las comillas y los tokens sin recortar.
 *
 * Uso: php scripts/ensename-la-columna.php VISTA DISPLAY campo [mas campos...]
 */

$carpeta = dirname(__DIR__) . '/config/sync';
$vista = $argv[1] ?? NULL;
$display = $argv[2] ?? NULL;
$queridos = array_slice($argv, 3);

if ($vista === NULL || $display === NULL || !$queridos) {
  echo "Uso: php scripts/ensename-la-columna.php VISTA DISPLAY campo [mas campos...]\n";
  exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
$datos = \Symfony\Component\Yaml\Yaml::parseFile("$carpeta/views.view.$vista.yml");
$campos = $datos['display'][$display]['display_options']['fields'] ?? [];

foreach ($queridos as $nombre) {
  echo "\n";
  echo str_repeat('=', 78) . "\n";
  echo " $nombre\n";
  echo str_repeat('=', 78) . "\n";
  if (!isset($campos[$nombre])) {
    echo "No esta en $vista / $display.\n";
    continue;
  }
  $campo = $campos[$nombre];

  // Fuera el relleno que traen todos los campos y que no dice nada.
  $relleno = [
    'make_link', 'path', 'absolute', 'external', 'replace_spaces', 'path_case',
    'trim_whitespace', 'alt', 'rel', 'link_class', 'target', 'nl2br',
    'max_length', 'word_boundary', 'ellipsis', 'more_link', 'more_link_text',
    'more_link_path', 'strip_tags', 'trim', 'preserve_tags', 'html',
    'element_type', 'element_class', 'element_label_type', 'element_label_class',
    'element_label_colon', 'element_wrapper_type', 'element_wrapper_class',
    'element_default_classes', 'group_type', 'admin_label',
  ];
  foreach ($relleno as $sobra) {
    unset($campo[$sobra], $campo['alter'][$sobra]);
  }
  if (isset($campo['alter']) && $campo['alter'] === []) {
    unset($campo['alter']);
  }

  echo \Symfony\Component\Yaml\Yaml::dump($campo, 6, 2, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
}
