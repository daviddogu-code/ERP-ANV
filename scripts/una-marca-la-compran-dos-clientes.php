<?php

/**
 * @file
 * Dos clientes pueden comprar la misma marca, y el orden lo pone la casa.
 *
 *   php vendor\bin\drush.php scr scripts/una-marca-la-compran-dos-clientes.php
 *
 * Esto es lo que el diseno viejo no podia decir, y no por poco: la marca tenia
 * UNA casilla de cliente y el producto tenia UNA casilla de cliente, asi que dos
 * empresas comprando la misma marca no se podia escribir de ninguna manera. La
 * unica salida era repetir la marca, con su logotipo y su catalogo duplicados.
 *
 * Asi que la prueba no comprueba que el campo exista -eso lo ve cualquiera-, sino
 * las dos cosas por las que se cambio:
 *
 *   1. Que la misma marca este en dos clientes a la vez.
 *   2. Que el orden sea el que se le da y no el alfabetico, porque de ese orden
 *      van a colgar las lineas de las proformas.
 *
 * Y una tercera de las que se escapan: que el campo no acepte terminos de otro
 * vocabulario. Un desplegable mal atado dejaria meter un color como si fuera una
 * marca, y eso no se ve hasta que sale impreso.
 *
 * Crea dos marcas y dos clientes de prueba, y los borra al final.
 */

use Drupal\Core\Form\EnforcedResponseException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

const CAMPO = 'field_tec_brands';

$gestor = \Drupal::entityTypeManager();
\Drupal::currentUser()->setAccount($gestor->getStorage('user')->load(1));

$nucleo = \Drupal::service('http_kernel');
$sesion = \Drupal::service('session');
$terminos = $gestor->getStorage('taxonomy_term');
$contactos = $gestor->getStorage('tec_crm');

$problemas = 0;
$creadas = [];

/**
 * Da una linea de veredicto y va contando lo que sale mal.
 */
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

/**
 * Pide una pagina como usuario 1.
 */
$pedir = function (string $ruta) use ($nucleo, $sesion): Response {
  $peticion = Request::create($ruta);
  $peticion->setSession($sesion);
  try {
    return $nucleo->handle($peticion, HttpKernelInterface::SUB_REQUEST, FALSE);
  }
  catch (EnforcedResponseException $e) {
    return $e->getResponse();
  }
  catch (HttpExceptionInterface $e) {
    return new Response('', $e->getStatusCode());
  }
  catch (\Throwable $e) {
    printf("      HA REVENTADO: %s: %s\n", get_class($e), $e->getMessage());
    return new Response('', 500);
  }
};

/**
 * Las marcas de un contacto, en el orden en que las tiene guardadas.
 */
$susMarcas = function (string $cid) use ($contactos): array {
  $ficha = $contactos->loadUnchanged($cid);
  return $ficha ? array_map(
    fn(array $v): string => (string) $v['target_id'],
    $ficha->get(CAMPO)->getValue()
  ) : [];
};

print "\n";
print "El campo\n";
print str_repeat('-', 70) . "\n";

$definiciones = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('tec_crm', 'tec_contact_organization');
$definicion = $definiciones[CAMPO] ?? NULL;

$mirar('el cliente tiene donde apuntar sus marcas', $definicion !== NULL);
if (!$definicion) {
  print "\n  Sin el campo no hay nada que probar.\n\n";
  return;
}

$mirar('y no le cabe una sola, sino todas las que compre',
  $definicion->getFieldStorageDefinition()->getCardinality() === -1,
  'caben ' . $definicion->getFieldStorageDefinition()->getCardinality());

$vocabularios = array_keys($definicion->getSetting('handler_settings')['target_bundles'] ?? []);
$mirar('y solo acepta marcas', $vocabularios === ['tec_brands'],
  implode(', ', $vocabularios));

$mirar('las personas de contacto tambien lo tienen',
  isset(\Drupal::service('entity_field.manager')
    ->getFieldDefinitions('tec_crm', 'tec_contact_person')[CAMPO]));

print "\n";
print "La misma marca en dos clientes\n";
print str_repeat('-', 70) . "\n";

// Nombres que ordenan al contrario de como se van a guardar, para que el orden
// alfabetico y el de la casa no puedan confundirse.
$aitana = $terminos->create(['vid' => 'tec_brands', 'name' => 'PRUEBA marca Aitana']);
$aitana->save();
$creadas[] = $aitana;
$zoe = $terminos->create(['vid' => 'tec_brands', 'name' => 'PRUEBA marca Zoe']);
$zoe->save();
$creadas[] = $zoe;

$uno = $contactos->create([
  'type' => 'tec_contact_organization',
  'title' => 'PRUEBA cliente uno',
  'field_tec_customer_code' => 'ZZUNO',
  CAMPO => [['target_id' => $zoe->id()], ['target_id' => $aitana->id()]],
]);
$uno->save();
$creadas[] = $uno;

$dos = $contactos->create([
  'type' => 'tec_contact_organization',
  'title' => 'PRUEBA cliente dos',
  'field_tec_customer_code' => 'ZZDOS',
  CAMPO => [['target_id' => $zoe->id()]],
]);
$dos->save();
$creadas[] = $dos;

$deUno = $susMarcas($uno->id());
$deDos = $susMarcas($dos->id());

$mirar('el primer cliente compra las dos', count($deUno) === 2, count($deUno) . ' marcas');
$mirar('y la marca Zoe esta en los dos clientes a la vez',
  in_array((string) $zoe->id(), $deUno, TRUE) && in_array((string) $zoe->id(), $deDos, TRUE));

print "\n";
print "El orden es el que se le da\n";
print str_repeat('-', 70) . "\n";

$mirar('se guardo Zoe antes que Aitana, contra el alfabeto',
  $deUno === [(string) $zoe->id(), (string) $aitana->id()],
  implode(' luego ', array_map(fn(string $tid): string => (string) $terminos->load($tid)?->label(), $deUno)));

// Darle la vuelta a mano tiene que aguantar el guardado, que es lo que hara la
// pantalla al arrastrar.
$uno->set(CAMPO, [['target_id' => $aitana->id()], ['target_id' => $zoe->id()]]);
$uno->save();
$mirar('y darle la vuelta la aguanta',
  $susMarcas($uno->id()) === [(string) $aitana->id(), (string) $zoe->id()],
  implode(' luego ', array_map(fn(string $tid): string => (string) $terminos->load($tid)?->label(), $susMarcas($uno->id()))));

print "\n";
print "Lo que no debe entrar\n";
print str_repeat('-', 70) . "\n";

$colores = $terminos->getQuery()->accessCheck(FALSE)->condition('vid', 'tec_colors')->range(0, 1)->execute();
if ($colores === []) {
  print "  No hay colores con los que probar.\n";
}
else {
  $intruso = $contactos->loadUnchanged($dos->id());
  $intruso->set(CAMPO, [['target_id' => reset($colores)]]);
  $mirar('un color no se puede colar como marca',
    $intruso->validate()->getByField(CAMPO)->count() > 0,
    'termino ' . reset($colores));
}

print "\n";
print "Las pantallas\n";
print str_repeat('-', 70) . "\n";

$respuesta = $pedir('/tec_crm/' . $uno->id() . '/edit');
$html = (string) $respuesta->getContent();
$mirar('el formulario del cliente carga', $respuesta->getStatusCode() === 200,
  'codigo ' . $respuesta->getStatusCode());
$mirar('trae una casilla por marca', str_contains($html, CAMPO . '[0][target_id]'));
// El peso de cada fila es lo que escribe el arrastre. Sin el, hay lista pero no
// hay orden que se pueda tocar.
$mirar('y el peso de cada fila, que es lo que mueve el arrastre',
  str_contains($html, CAMPO . '[0][_weight]'));

$respuesta = $pedir('/admin/structure/taxonomy/manage/tec_brands/add');
$html = (string) $respuesta->getContent();
$mirar('el formulario de la marca ya no pregunta por su cliente',
  $respuesta->getStatusCode() === 200 && !str_contains($html, 'field_tec_customer'),
  'codigo ' . $respuesta->getStatusCode());

$respuesta = $pedir('/taxonomy/term/' . $zoe->id());
$mirar('y su ficha tampoco lo ensena',
  $respuesta->getStatusCode() === 200
  && !str_contains((string) $respuesta->getContent(), 'field--name-field-tec-customer'),
  'codigo ' . $respuesta->getStatusCode());

foreach (array_reverse($creadas) as $ficha) {
  $almacen = $gestor->getStorage($ficha->getEntityTypeId());
  $almacen->loadUnchanged($ficha->id())?->delete();
}

print "\n" . str_repeat('=', 70) . "\n";
if ($problemas === 0) {
  print "  Todo bien. Las dos marcas y los dos clientes de prueba se han borrado.\n\n";
}
else {
  printf("  %d cosas mal. Lo creado se ha borrado.\n\n", $problemas);
}
