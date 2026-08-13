<?php

/**
 * @file
 * Lista todas las versiones publicadas de un proyecto de drupal.org, con su
 * compatibilidad de nucleo.
 *
 *   php scripts/versiones-de.php simple_popup_views pdf_serialization
 *
 * Complementa a hay-version-para-11.php: aquel resume y este enseña el detalle
 * de un proyecto concreto, que es lo que hace falta cuando la respuesta corta
 * es "no hay version para la 11" y hay que decidir que hacer con el.
 *
 * Solo lee.
 */

$proyectos = array_slice($argv, 1);
if (!$proyectos) {
  echo "Uso: php scripts/versiones-de.php <proyecto> [proyecto...]\n";
  exit(1);
}

foreach ($proyectos as $proyecto) {
  $ch = curl_init("https://updates.drupal.org/release-history/$proyecto/current");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => TRUE,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => TRUE,
    CURLOPT_USERAGENT => 'tec-erp-upgrade-check',
  ]);
  $xml = curl_exec($ch);
  $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  echo "\n" . str_repeat('=', 78) . "\n";

  if ($codigo !== 200 || !$xml) {
    echo "$proyecto: drupal.org responde $codigo\n";
    continue;
  }

  $doc = @simplexml_load_string($xml);
  if (!$doc) {
    echo "$proyecto: no se entiende la respuesta\n";
    continue;
  }

  printf("%s  (%s)\n", (string) $doc->title, $proyecto);
  printf("  estado del proyecto: %s\n", (string) $doc->project_status);
  printf("  ramas con soporte:   %s\n", (string) ($doc->supported_branches ?? 'ninguna'));
  foreach ($doc->terms->term ?? [] as $t) {
    if (in_array((string) $t->name, ['Maintenance status', 'Development status'], TRUE)) {
      printf("  %-20s %s\n", strtolower((string) $t->name) . ':', (string) $t->value);
    }
  }
  echo str_repeat('-', 78) . "\n";
  printf("  %-22s %-12s %-8s %s\n", 'version', 'fecha', 'avisos', 'nucleo');
  echo '  ' . str_repeat('-', 74) . "\n";

  foreach ($doc->releases->release ?? [] as $r) {
    if ((string) $r->status !== 'published') {
      continue;
    }
    printf(
      "  %-22s %-12s %-8s %s\n",
      (string) $r->version,
      date('Y-m-d', (int) $r->date),
      isset($r->security['covered']) && (string) $r->security['covered'] === '1' ? 'si' : '-',
      (string) ($r->core_compatibility ?? '?')
    );
  }
}
echo "\n";
