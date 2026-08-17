<?php

/**
 * @file
 * Quien creo la talla "20 oz" y cuando, para saber si se borra o se acepta.
 *
 * La talla que sobra no es basura de una prueba: se llama "20 oz" y encaja en la
 * serie que ya estaba -6, 8, 10, 12, 14, 16 y 18 oz-, o sea que tiene toda la
 * pinta de ser un dato de verdad que alguien ha metido. Borrarla para que la
 * comprobacion cuadre seria tirar trabajo del dueno a la basura, asi que primero
 * hay que saber de donde viene.
 *
 * Lo que se mira: quien la firma, cuando, si la referencia algun BoM o
 * alguna variacion, y que dice el registro a esa hora.
 *
 * Ya contesto: el registro dice "Created new term 20 oz" desde
 * /admin/structure/taxonomy/manage/tec_sizes/add con el usuario 1, o sea que la
 * metio el dueno por el formulario mientras se arreglaba la portada de marcas. No
 * habia nada que borrar; lo que estaba viejo era la cifra de referencia de
 * scripts/comprobacion.php, que se subio de 14 a 15.
 *
 * Se queda por dos motivos: la primera sospecha -que fuera basura que
 * comprobacion.php se dejara detras, porque su apartado 3 crea una variacion de
 * talla- era razonable y era falsa, y este guion es la unica forma de volver a
 * distinguir las dos cosas la proxima vez que una cuenta no cuadre. Que una cifra
 * de referencia falle no significa que algo este roto: significa que hay que
 * mirar quien la movio.
 *
 * No escribe nada.
 *
 * Uso: drush php:script scripts/quien-creo-la-talla-de-mas
 */

$terminos = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$ids = $terminos->getQuery()->accessCheck(FALSE)
  ->condition('vid', 'tec_sizes')
  ->sort('tid', 'DESC')
  ->range(0, 1)
  ->execute();
$termino = $terminos->load(reset($ids));

echo "\n";
echo str_repeat('=', 82) . "\n";
printf(" La talla mas nueva: %s (tid %s)\n", $termino->label(), $termino->id());
echo str_repeat('=', 82) . "\n\n";

foreach ($termino->getFields() as $nombre => $campo) {
  $valor = $campo->isEmpty() ? '(vacio)' : json_encode($campo->getValue(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  printf("  %-26s %s\n", $nombre, mb_strimwidth($valor, 0, 90, '..'));
}

$duenoId = $termino->hasField('revision_user') && !$termino->get('revision_user')->isEmpty()
  ? $termino->get('revision_user')->target_id
  : NULL;
if ($duenoId) {
  $dueno = \Drupal::entityTypeManager()->getStorage('user')->load($duenoId);
  printf("\n  la firma: usuario %s (%s)\n", $duenoId, $dueno ? $dueno->getAccountName() : '?');
}

$cuando = (int) $termino->get('changed')->value;
printf("  la hora:  %s\n", date('Y-m-d H:i:s', $cuando));

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " La usa alguien\n";
echo str_repeat('=', 82) . "\n\n";

// Una talla que ya cuelga de un producto o de un BoM es un dato en uso, y
// eso zanja la discusion: no se toca.
$usos = 0;
foreach (['tec_product', 'tec_inventory', 'tec_line_item'] as $tipo) {
  $almacen = \Drupal::entityTypeManager()->getStorage($tipo);
  foreach (\Drupal::service('entity_field.manager')->getFieldStorageDefinitions($tipo) as $nombreCampo => $definicion) {
    if ($definicion->getType() !== 'entity_reference') {
      continue;
    }
    $ajustes = $definicion->getSettings();
    if (($ajustes['target_type'] ?? '') !== 'taxonomy_term') {
      continue;
    }
    try {
      $encontrados = $almacen->getQuery()->accessCheck(FALSE)
        ->condition($nombreCampo, $termino->id())
        ->count()->execute();
    }
    catch (\Throwable $e) {
      continue;
    }
    if ($encontrados) {
      printf("  %s.%s: %s fichas la usan\n", $tipo, $nombreCampo, $encontrados);
      $usos += $encontrados;
    }
  }
}
if (!$usos) {
  echo "  Nadie la usa todavia. Que no se use no la convierte en basura: una talla\n";
  echo "  recien creada empieza siempre sin usar.\n";
}

echo "\n";
echo str_repeat('=', 82) . "\n";
echo " Que dice el registro a esa hora\n";
echo str_repeat('=', 82) . "\n\n";

// El registro es lo que distingue "lo ha metido una persona por el formulario" de
// "lo ha fabricado un automatismo": una pagina de formulario deja su rastro con la
// ruta y el usuario.
$filas = \Drupal::database()->select('watchdog', 'w')
  ->fields('w', ['wid', 'type', 'message', 'variables', 'location', 'uid', 'timestamp'])
  ->condition('timestamp', [$cuando - 120, $cuando + 120], 'BETWEEN')
  ->orderBy('wid')
  ->range(0, 40)
  ->execute()
  ->fetchAll();

if (!$filas) {
  echo "  El registro no dice nada en los dos minutos de alrededor.\n";
}
foreach ($filas as $fila) {
  $variables = @unserialize($fila->variables) ?: [];
  $mensaje = strtr($fila->message, is_array($variables) ? array_map(
    static fn ($v): string => is_scalar($v) ? (string) $v : '',
    $variables
  ) : []);
  printf(
    "  %-6s %-14s uid %-3s %s\n         %s\n",
    $fila->wid,
    $fila->type,
    $fila->uid,
    date('H:i:s', (int) $fila->timestamp),
    mb_strimwidth(trim(preg_replace('/\s+/', ' ', strip_tags($mensaje))), 0, 130, '..')
  );
  if ($fila->location) {
    printf("         %s\n", mb_strimwidth($fila->location, 0, 130, '..'));
  }
}

echo "\n";
echo str_repeat('=', 82) . "\n\n";
