<?php

/**
 * @file
 * Pide una pagina del ERP por dentro y dice con que codigo responde. Se lanza:
 *   drush scr scripts/probar-pagina.php
 *
 * Sirve para comprobar paginas que fallaban sin tener que abrir el navegador
 * ni pelearse con el inicio de sesion. Entra como el usuario 1.
 */

$rutas = [
  '/tec_order/348',
  '/tec_order/348/edit',
  '/admin/content',
  '/o/queue',
  '/stock',
  '/production/log',
  '/production/report',
  '/tec_crm/1',
  '/tec_crm/1/edit',
  '/tec_product/304',
  '/admin/structure/views',
  '/admin/modules',
  '/admin/reports/status',
  '/admin/config/development/performance',
];

$cuenta = \Drupal\user\Entity\User::load(1);
\Drupal::currentUser()->setAccount($cuenta);

$kernel = \Drupal::service('http_kernel');
$peticion_original = \Drupal::request();

echo "\n";
foreach ($rutas as $ruta) {
  $peticion = \Symfony\Component\HttpFoundation\Request::create($ruta);
  $peticion->setSession($peticion_original->getSession());

  try {
    $respuesta = $kernel->handle($peticion, \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST);
    $codigo = $respuesta->getStatusCode();
    $bytes = strlen($respuesta->getContent());
    printf("  %-24s %s   %d bytes\n", $ruta, $codigo, $bytes);
  }
  catch (\Throwable $e) {
    printf("  %-24s EXCEPCION: %s\n", $ruta, get_class($e));
    printf("  %-24s   %s\n", '', $e->getMessage());
    printf("  %-24s   %s:%d\n", '', str_replace(DRUPAL_ROOT, '', $e->getFile()), $e->getLine());
  }
}
echo "\n";
