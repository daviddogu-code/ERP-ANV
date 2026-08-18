<?php

/**
 * @file
 * El producto suelta al cliente: fuera del formulario, de la ficha y de la ECA que
 * duplica productos.
 *
 *   php vendor\bin\drush.php scr scripts/el-producto-suelta-al-cliente.php
 *
 * Con las pantallas ya recableadas para subir por la marca, al campo de cliente del
 * producto solo le quedan tres sitios donde sigue puesto:
 *
 *   el formulario  un desplegable de cliente al crear o editar un producto
 *   la ficha       una linea «Customer» dentro de la maqueta
 *   la ECA         al duplicar un producto, copiaba el cliente al nuevo
 *
 * Y una cuarta cosa, que es la que mas se nota: el campo es OBLIGATORIO, asi que hoy
 * no se puede crear un producto sin decir de que cliente es. Eso se suelta aqui.
 *
 * Se hace en este orden y no todo a la vez con el borrado: al terminar este guion el
 * ERP tiene que quedar funcionando, con el campo todavia en su sitio pero sin que
 * nadie lo pida ni lo ensene. Si el borrado se torciera, no se habria roto nada.
 *
 * Se puede lanzar dos veces: comprueba antes de escribir.
 */

const CAMPO = 'field_tec_customer';
const MODELO = 'process_llpx4tp';
const PASO = 'Activity_171sw4c';

$gestor = \Drupal::entityTypeManager();
$hechas = [];

print "\n";
print "=====================================================================\n";
print "  El producto suelta al cliente\n";
print "=====================================================================\n\n";

// --- 1. La ECA que duplica productos ---------------------------------------
// Un paso no se quita a secas: los pasos van encadenados, y si se borra el del medio
// la cadena se corta y todo lo que venia detras deja de pasar. Hay que coser: quien
// apuntaba al paso pasa a apuntar a donde apuntaba el paso.
$eca = $gestor->getStorage('eca')->load(MODELO);
if (!$eca) {
  print "  La ECA de duplicar productos no esta. Nada que hacer ahi.\n";
}
else {
  $pasos = $eca->get('actions') ?? [];
  if (!isset($pasos[PASO])) {
    print "  La ECA ya no copia el cliente.\n";
  }
  else {
    $detras = $pasos[PASO]['successors'] ?? [];
    $cosidos = 0;

    // Los enlaces pueden llevar condicion, y ahi esta el cuidado: el que sale de este
    // paso lleva una -«solo si el producto tiene colores»- y hay que conservarla, o al
    // duplicar un producto sin colores empezaria a hacer cosas que antes no hacia. Se
    // conserva sola al copiar los enlaces tal cual. Lo que no se puede es fundir DOS
    // condiciones en un enlace, asi que si las hubiera a los dos lados, se para.
    foreach ($pasos as $id => $paso) {
      foreach ($paso['successors'] ?? [] as $i => $siguiente) {
        if (($siguiente['id'] ?? '') !== PASO) {
          continue;
        }
        $entrante = trim((string) ($siguiente['condition'] ?? ''));
        $cosidos++;

        $nuevos = [];
        foreach ($detras as $unDetras) {
          $suya = trim((string) ($unDetras['condition'] ?? ''));
          if ($entrante !== '' && $suya !== '') {
            print "  OJO: hay condicion a los dos lados del paso del cliente. No lo toco.\n";
            return;
          }
          $unDetras['condition'] = $suya !== '' ? $suya : $entrante;
          $nuevos[] = $unDetras;
        }
        array_splice($pasos[$id]['successors'], $i, 1, $nuevos);
      }
    }

    unset($pasos[PASO]);
    $eca->set('actions', $pasos);
    $eca->save();
    $hechas[] = 'la ECA de duplicar ya no copia el cliente (cadena cosida en ' . $cosidos . ' sitio/s)';
  }
}

// --- 1b. Y el dibujo de esa misma ECA --------------------------------------
// Una ECA vive en dos ficheros: el ejecutable, que es lo que corre, y el dibujo, que
// es lo que edita la pantalla de ECA. El editor REGENERA el ejecutable desde el dibujo
// cada vez que alguien guarda, asi que un paso quitado solo del ejecutable vuelve al
// primer clic de otro. El dibujo es BPMN, o sea que el paso no es una linea sino una
// caja con dos flechas, y hay que empalmar las flechas al quitar la caja.
$dibujo = \Drupal::configFactory()->getEditable('eca.model.' . MODELO);
$xml = new DOMDocument();
$xml->preserveWhiteSpace = TRUE;

if (!$dibujo->isNew() && @$xml->loadXML((string) $dibujo->get('modeldata'))) {
  $buscador = new DOMXPath($xml);
  $caja = $buscador->query(sprintf('//*[@id="%s"]', PASO))->item(0);

  if (!$caja) {
    print "  El dibujo de la ECA ya no tiene el paso del cliente.\n";
  }
  else {
    $flechasQueEntran = $buscador->query('*[local-name()="incoming"]', $caja);
    $flechasQueSalen = $buscador->query('*[local-name()="outgoing"]', $caja);

    if ($flechasQueEntran->count() !== 1 || $flechasQueSalen->count() !== 1) {
      printf("  OJO: en el dibujo el paso tiene %d flechas que entran y %d que salen. No lo toco.\n",
        $flechasQueEntran->count(), $flechasQueSalen->count());
      return;
    }

    $entra = trim($flechasQueEntran->item(0)->textContent);
    $sale = trim($flechasQueSalen->item(0)->textContent);
    $laQueEntra = $buscador->query(sprintf('//*[@id="%s"]', $entra))->item(0);
    $laQueSale = $buscador->query(sprintf('//*[@id="%s"]', $sale))->item(0);
    $antes = $laQueEntra?->getAttribute('sourceRef') ?? '';

    if ($antes === '' || !$laQueSale) {
      print "  OJO: en el dibujo no encuentro de donde viene o a donde va. No lo toco.\n";
      return;
    }

    // La flecha de salida pasa a nacer en el paso anterior, y con ella se conserva su
    // condicion, que va escrita en la propia flecha.
    $laQueSale->setAttribute('sourceRef', $antes);
    foreach ($buscador->query(sprintf('//*[@id="%s"]/*[local-name()="outgoing"]', $antes)) as $suSalida) {
      if (trim($suSalida->textContent) === $entra) {
        $suSalida->nodeValue = $sale;
      }
    }

    $caja->parentNode->removeChild($caja);
    $laQueEntra->parentNode->removeChild($laQueEntra);

    // Y lo que se ve: la caja pintada y la flecha que ya no lleva a ningun sitio. La
    // flecha que queda se hace nacer en el borde derecho de la caja anterior, que si
    // no sale de la nada y el editor la pinta cruzando el diagrama.
    $bordes = $buscador->query(sprintf('//*[@bpmnElement="%s"]/*[local-name()="Bounds"]', $antes))->item(0);
    foreach ($buscador->query(sprintf('//*[@bpmnElement="%s"]', $sale)) as $pintada) {
      $puntos = $buscador->query('*[local-name()="waypoint"]', $pintada);
      if ($bordes && $puntos->count() > 0) {
        $puntos->item(0)->setAttribute('x', (string) ((int) $bordes->getAttribute('x') + (int) $bordes->getAttribute('width')));
        $puntos->item(0)->setAttribute('y', (string) ((int) $bordes->getAttribute('y') + (int) round($bordes->getAttribute('height') / 2)));
      }
    }
    foreach ([PASO, $entra] as $muerto) {
      foreach ($buscador->query(sprintf('//*[@bpmnElement="%s"]', $muerto)) as $pintada) {
        $pintada->parentNode->removeChild($pintada);
      }
    }

    $dibujo->set('modeldata', $xml->saveXML())->save();
    $hechas[] = 'y su dibujo tampoco, con la flecha empalmada de «' . $antes . '» a «' . $sale . '»';
  }
}

// --- 2. El formulario del producto -----------------------------------------
$formulario = $gestor->getStorage('entity_form_display')->load('tec_product.tec_product.default');
if ($formulario && $formulario->getComponent(CAMPO)) {
  $formulario->removeComponent(CAMPO)->save();
  $hechas[] = 'fuera el desplegable de cliente del formulario de producto';
}

// --- 3. La ficha del producto ----------------------------------------------
// Aqui el campo no esta en la lista de arriba sino DENTRO de la maqueta, que es donde
// no se le ve si solo se mira la lista. Se busca por secciones.
$ficha = $gestor->getStorage('entity_view_display')->load('tec_product.tec_product.default');
if ($ficha) {
  $fuera = 0;
  foreach ($ficha->getThirdPartySetting('layout_builder', 'sections') ?? [] as $seccion) {
    foreach ($seccion->getComponents() as $uuid => $trozo) {
      if (str_ends_with((string) ($trozo->getPluginId() ?? ''), ':' . CAMPO)) {
        $seccion->removeComponent($uuid);
        $fuera++;
      }
    }
  }
  if ($fuera > 0) {
    // El objeto de las secciones se modifica en su sitio; hay que volver a guardarlo
    // para que la ficha se entere.
    $ficha->save();
    $hechas[] = 'fuera la linea «Customer» de la maqueta de la ficha (' . $fuera . ')';
  }
}

// --- 4. Y deja de ser obligatorio ------------------------------------------
$campo = $gestor->getStorage('field_config')->load('tec_product.tec_product.' . CAMPO);
if ($campo && $campo->isRequired()) {
  $campo->setRequired(FALSE)->save();
  $hechas[] = 'el cliente ya no es obligatorio para crear un producto';
}

foreach ($hechas as $hecha) {
  print '  ' . $hecha . "\n";
}
if ($hechas === []) {
  print "  Ya estaba todo suelto.\n";
}

$sigueElCampo = isset(\Drupal::service('entity_field.manager')
  ->getFieldDefinitions('tec_product', 'tec_product')[CAMPO]);

print "\n=====================================================================\n";
printf("  %d cosas hechas. %s\n", count($hechas), $sigueElCampo
  ? 'El campo sigue ahi, pero ya no lo usa nadie.'
  : 'El campo ya se borro el 19 de agosto de 2026.');
print "=====================================================================\n\n";
