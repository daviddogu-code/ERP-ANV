<?php

/**
 * @file
 * Deja un solo codigo corto por ficha del CRM, y lo hace obligatorio.
 *
 *   php vendor\bin\drush.php scr scripts/un-solo-codigo-corto.php
 *
 * Habia dos campos para la misma idea: field_tec_customer_code, con la etiqueta
 * "Short code", seis letras de tope y la explicacion de que sale en el numero del
 * pedido, y field_tec_supplier_code, "Supplier code", "Internal supplier code",
 * sin tope. La misma empresa puede ser cliente y proveedor a la vez -KJ lo es-, y
 * con dos campos habia que escribir el codigo dos veces en la misma ficha y
 * acordarse de mantenerlos iguales, o los pedidos de venta y los de compra de esa
 * empresa saldrian con codigos distintos.
 *
 * Se queda el del cliente, que es el que ya tiene la etiqueta buena, el tope de
 * seis y el unico dato que existe: KJ. El del proveedor esta vacio en las dos
 * fichas que hay, asi que retirarlo no pierde nada. En una base con dos fichas
 * esto cuesta un minuto; con doscientas habria sido una migracion.
 *
 * El codigo pasa a ser obligatorio porque los pedidos de compra se numeran por
 * proveedor: sin codigo, dos proveedores querrian llamarse los dos 26-001, y un
 * numero que dos pedidos comparten no es un numero.
 *
 * Solo lo lleva la ficha de organizacion, no la de persona, y no es un descuido:
 * el proveedor de un pedido de compra solo puede ser una organizacion -asi esta
 * puesto en field_tec_vendor, tanto en el pedido como en la linea-, asi que el
 * codigo no se lee nunca de una persona.
 *
 * Se puede lanzar dos veces.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

const VIEJO = 'field_tec_supplier_code';
const NUEVO = 'field_tec_customer_code';

$problemas = 0;
$mirar = function (string $que, bool $bien, string $detalle = '') use (&$problemas): void {
  printf("  %-6s %s%s\n", $bien ? 'BIEN' : 'MAL', $que, $detalle === '' ? '' : ': ' . $detalle);
  if (!$bien) {
    $problemas++;
  }
};

$gestor = \Drupal::entityTypeManager();
$config = \Drupal::configFactory();

echo "\n";
echo "1. Las fichas que se quedarian sin codigo\n";
echo str_repeat('-', 78) . "\n";

// Obligatorio con fichas vacias es una trampa: el formulario se niega a guardar y
// no dice por que hasta que bajas al campo. Se les pone un codigo sacado del
// nombre, y se dice cual, para que se pueda cambiar a mano.
$almacen_crm = $gestor->getStorage('tec_crm');
$ids = $almacen_crm->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'tec_contact_organization')
  ->execute();

$usados = [];
$sin_codigo = [];
foreach ($almacen_crm->loadMultiple($ids) as $ficha) {
  $codigo = trim((string) ($ficha->get(NUEVO)->value ?? ''));
  if ($codigo !== '') {
    $usados[strtoupper($codigo)] = TRUE;
    printf("         %-24s ya tiene '%s'\n", $ficha->label(), $codigo);
    continue;
  }
  $sin_codigo[] = $ficha;
}

foreach ($sin_codigo as $ficha) {
  $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $ficha->label()));
  $codigo = substr($base, 0, 6) ?: 'X';
  $n = 1;
  while (isset($usados[$codigo])) {
    $codigo = substr($base, 0, 5) . $n;
    $n++;
  }
  $usados[$codigo] = TRUE;
  $ficha->set(NUEVO, $codigo);
  $ficha->save();
  printf("         %-24s sin codigo, se le pone '%s'\n", $ficha->label(), $codigo);
}
if (!$sin_codigo) {
  echo "         ninguna, todas tenian codigo\n";
}

echo "\n";
echo "2. Las pantallas donde se ensenaba el codigo del proveedor\n";
echo str_repeat('-', 78) . "\n";

// El codigo del proveedor se ensenaba en varias pantallas de la ficha; el del
// cliente en ninguna. Para no perder de vista un dato que ya se veia, el que se
// queda ocupa el sitio del que se va, con su mismo peso y su mismo formato. En
// las fichas de persona no hay recambio, asi que se quita y punto.
foreach ($config->listAll('core.entity_view_display.tec_crm.') as $nombre) {
  $pantalla = $config->getEditable($nombre);
  $persona = str_contains($nombre, '.tec_contact_person.');
  $tocado = [];

  // La lista normal de campos visibles.
  $visible = $pantalla->get('content.' . VIEJO);
  if ($visible !== NULL) {
    if (!$persona && $pantalla->get('content.' . NUEVO) === NULL) {
      $pantalla->set('content.' . NUEVO, $visible);
      $tocado[] = 'campo visible, ahora el que se queda';
    }
    else {
      $tocado[] = 'campo visible, quitado';
    }
    $pantalla->clear('content.' . VIEJO);
  }

  // Y las secciones de Layout Builder, que el nucleo no limpia al borrar un
  // campo: se quedaria un bloque roto pintando "This block is broken" en la
  // ficha del contacto.
  $secciones = $pantalla->get('third_party_settings.layout_builder.sections');
  if (is_array($secciones)) {
    $cambio = FALSE;
    foreach ($secciones as $s => $seccion) {
      foreach ($seccion['components'] ?? [] as $c => $componente) {
        $id = $componente['configuration']['id'] ?? '';
        if (!str_contains($id, VIEJO)) {
          continue;
        }
        if ($persona) {
          unset($secciones[$s]['components'][$c]);
          $tocado[] = 'bloque de la maqueta, quitado';
        }
        else {
          $secciones[$s]['components'][$c]['configuration']['id'] = str_replace(VIEJO, NUEVO, $id);
          $tocado[] = 'bloque de la maqueta, ahora el que se queda';
        }
        $cambio = TRUE;
      }
    }
    if ($cambio) {
      $pantalla->set('third_party_settings.layout_builder.sections', $secciones);
    }
  }

  if ($tocado) {
    $pantalla->save();
    printf("         %-52s %s\n", substr($nombre, strlen('core.entity_view_display.tec_crm.')), implode('; ', $tocado));
  }
}

echo "\n";
echo "3. Los formularios\n";
echo str_repeat('-', 78) . "\n";

// En el formulario de la organizacion el que se va vivia en el grupo "Supplier
// purchase defaults", que solo se ve al marcar Supplier. El que se queda ya esta
// arriba, en el grupo general, que es donde tiene que estar algo que sirve para
// las dos cosas. Asi que aqui solo hay que sacarlo del grupo.
foreach ($config->listAll('core.entity_form_display.tec_crm.') as $nombre) {
  $pantalla = $config->getEditable($nombre);
  $tocado = [];

  if ($pantalla->get('content.' . VIEJO) !== NULL) {
    $pantalla->clear('content.' . VIEJO);
    $tocado[] = 'fuera del formulario';
  }
  $grupos = $pantalla->get('third_party_settings.field_group');
  if (is_array($grupos)) {
    $cambio = FALSE;
    foreach ($grupos as $grupo => $datos) {
      $hijos = $datos['children'] ?? [];
      if (!in_array(VIEJO, $hijos, TRUE)) {
        continue;
      }
      $grupos[$grupo]['children'] = array_values(array_diff($hijos, [VIEJO]));
      $tocado[] = 'fuera del grupo ' . $grupo;
      $cambio = TRUE;
    }
    if ($cambio) {
      $pantalla->set('third_party_settings.field_group', $grupos);
    }
  }

  if ($tocado) {
    $pantalla->save();
    printf("         %-52s %s\n", substr($nombre, strlen('core.entity_form_display.tec_crm.')), implode('; ', $tocado));
  }
}

echo "\n";
echo "4. El campo que se queda\n";
echo str_repeat('-', 78) . "\n";

$campo = FieldConfig::loadByName('tec_crm', 'tec_contact_organization', NUEVO);
if (!$campo) {
  echo "  No encuentro el campo que se queda. Se para aqui.\n";
  return;
}
$campo->setLabel('Short code');
$campo->setDescription('Short code for order numbers (max 6 characters), e.g. ACTA. It goes on both sides: sales orders come out as ACTA 26-001 and purchase orders as ACTA 26-001 too. Required, because the number would repeat without it.');
$campo->setRequired(TRUE);
$campo->save();

$campo = FieldConfig::loadByName('tec_crm', 'tec_contact_organization', NUEVO);
$mirar('se llama Short code', $campo->getLabel() === 'Short code');
$mirar('es obligatorio', $campo->isRequired());
$mirar('la explicacion habla de compras', str_contains($campo->getDescription(), 'purchase orders'));

echo "\n";
echo "5. El campo que se retira\n";
echo str_repeat('-', 78) . "\n";

$borrados = 0;
foreach (['tec_contact_person', 'tec_contact_organization'] as $tipo) {
  $viejo = FieldConfig::loadByName('tec_crm', $tipo, VIEJO);
  if ($viejo) {
    $viejo->delete();
    $borrados++;
    printf("         fuera de %s\n", $tipo);
  }
}
$deposito = FieldStorageConfig::loadByName('tec_crm', VIEJO);
if ($deposito) {
  $deposito->delete();
  echo "         y su deposito\n";
}
if (!$borrados && !$deposito) {
  echo "         ya no estaba\n";
}

// Borrar un campo en Drupal deja los datos marcados para purgar. Estaban vacios,
// pero se purga igual para no dejar tablas fantasma.
try {
  field_purge_batch(200);
}
catch (\Throwable $e) {
  printf("         la purga se ha quejado: %s\n", $e->getMessage());
}

$mirar('el campo del proveedor ya no existe', !FieldStorageConfig::loadByName('tec_crm', VIEJO));
$definiciones = \Drupal::service('entity_field.manager')->getFieldDefinitions('tec_crm', 'tec_contact_organization');
$mirar('la organizacion tiene un solo codigo',
  isset($definiciones[NUEVO]) && !isset($definiciones[VIEJO]),
  implode(', ', array_values(array_filter(array_keys($definiciones), fn($n) => str_contains($n, '_code')))));

echo "\n";
echo "6. Que no queda nada apuntando al que se fue\n";
echo str_repeat('-', 78) . "\n";

$restos = [];
foreach ($config->listAll() as $nombre) {
  $texto = json_encode($config->get($nombre)->getRawData());
  if ($texto !== FALSE && str_contains($texto, VIEJO)) {
    $restos[] = $nombre;
  }
}
$mirar('ninguna configuracion lo menciona', !$restos, $restos ? implode(', ', $restos) : 'limpio');

echo "\n";
echo $problemas === 0
  ? "Un solo codigo corto por ficha, obligatorio, y sirve para las dos cosas.\n\n"
  : "$problemas cosa(s) mal.\n\n";
