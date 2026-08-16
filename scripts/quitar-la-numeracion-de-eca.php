<?php

/**
 * @file
 * Takes the order numbering out of the two ECA processes that create orders.
 *
 * Why this is surgery on the drawing and not on the executable file: an ECA
 * process lives in two config objects. eca.eca.PROCESS is what runs, and
 * eca.model.PROCESS is the BPMN drawing the visual editor shows. The editor
 * regenerates the first from the second every time somebody presses save.
 *
 * Both processes had already been patched in the executable file only. The
 * drawings still said "[current-date:custom:y]-[soCount]", with no short code
 * and no padding, and had no trace of the two padding steps. So the numbering
 * we have was one click away from vanishing: open the process in the editor,
 * press save, and sales orders go back to being called "26-1".
 *
 * So the drawing is edited here, and then handed to ECA's own modeller, which
 * regenerates the executable file from it. That way the two agree when this
 * finishes, which is the only state that does not rot.
 *
 * What changes in each process, and it is the same change twice:
 *
 * 1. The numbering chain goes: query a counting view, count the rows, set the
 *    title. The name now comes from OrderNumber, called from a presave hook, so
 *    these three steps have nothing left to do.
 *
 * 2. Three steps get rotated. Today each process creates the order, saves it,
 *    and only then sets the customer or the supplier. That order was harmless
 *    while the name came later; now the name is decided at the first save, and
 *    at that moment the order has nobody attached, so it would be born without
 *    its short code. Setting the contact before the save fixes it, and the save
 *    step that follows is the one that writes it.
 */

use Drupal\Core\Serialization\Yaml;

$modeller_services = \Drupal::service('eca.service.modeller');
$config_factory = \Drupal::configFactory();

/**
 * The three steps to drop and the three to rotate, per process.
 *
 * Same activity ids in both processes because one was copied from the other.
 */
$jobs = [
  'process_sclj26d' => [
    'que' => 'ventas',
    'create' => 'Activity_0yxiix4',
    'save' => 'Activity_0eek2xm',
    'contact' => 'Activity_01wk5lh',
    'chain' => ['Activity_1ss3tdk', 'Activity_014crcd', 'Activity_0xwr7vw'],
    'after' => 'Activity_1yw0bq2',
  ],
  'process_kryibry' => [
    'que' => 'compras',
    'create' => 'Activity_0yxiix4',
    'save' => 'Activity_0eek2xm',
    'contact' => 'Activity_01wk5lh',
    'chain' => ['Activity_0no2rub', 'Activity_1ok286e', 'Activity_1e5fwyj'],
    'after' => 'Activity_1yw0bq2',
  ],
];

$snapshot_dir = DRUPAL_ROOT . '/scripts/temp-eca-antes';
if (!is_dir($snapshot_dir)) {
  mkdir($snapshot_dir, 0777, TRUE);
}

$fallos = 0;

foreach ($jobs as $process_id => $job) {
  echo "=== {$process_id} ({$job['que']}) ===\n";

  // Snapshot first. The regenerated executable file is compared against this
  // afterwards, because the drawing may be behind in ways nobody wrote down.
  $before = $config_factory->get('eca.eca.' . $process_id)->getRawData();
  file_put_contents($snapshot_dir . '/' . $process_id . '.yml', Yaml::encode($before));

  $model = $config_factory->get('eca.model.' . $process_id);
  $xml = (string) $model->get('modeldata');
  if ($xml === '') {
    echo "  NO hay dibujo. Nada que hacer.\n";
    $fallos++;
    continue;
  }

  $dom = new DOMDocument();
  $dom->preserveWhiteSpace = FALSE;
  $dom->formatOutput = TRUE;
  if (!$dom->loadXML($xml)) {
    echo "  El dibujo no se puede leer como XML.\n";
    $fallos++;
    continue;
  }
  $xpath = new DOMXPath($dom);

  /**
   * The sequence flow joining two given activities, or NULL.
   */
  $flow_between = static function (string $from, string $to) use ($xpath): ?DOMElement {
    $found = $xpath->query(sprintf('//*[local-name()="sequenceFlow"][@sourceRef="%s"][@targetRef="%s"]', $from, $to));
    return $found->length ? $found->item(0) : NULL;
  };

  /**
   * Any element carrying the given id.
   */
  $by_id = static function (string $id) use ($xpath): ?DOMElement {
    $found = $xpath->query(sprintf('//*[@id="%s"]', $id));
    return $found->length ? $found->item(0) : NULL;
  };

  /**
   * The diagram shape or edge drawn for a given element.
   */
  $drawing_of = static function (string $id) use ($xpath): ?DOMElement {
    $found = $xpath->query(sprintf('//*[@bpmnElement="%s"]', $id));
    return $found->length ? $found->item(0) : NULL;
  };

  $chain = $job['chain'];

  // The three flows that survive, and get new ends.
  $flow_a = $flow_between($job['create'], $job['save']);
  $flow_b = $flow_between($job['save'], $job['contact']);
  $flow_c = $flow_between($job['contact'], $chain[0]);

  // The three that go, along with the steps they join.
  $doomed_flows = [
    $flow_between($chain[0], $chain[1]),
    $flow_between($chain[1], $chain[2]),
    $flow_between($chain[2], $job['after']),
  ];

  if (!$flow_a || !$flow_b || !$flow_c || in_array(NULL, $doomed_flows, TRUE)) {
    echo "  El dibujo no tiene la forma esperada. Ya estaba operado?\n";
    foreach (['crear->guardar' => $flow_a, 'guardar->contacto' => $flow_b, 'contacto->cadena' => $flow_c] as $name => $flow) {
      echo sprintf("    %-22s %s\n", $name, $flow ? 'esta' : 'NO esta');
    }
    $fallos++;
    continue;
  }

  $id_a = $flow_a->getAttribute('id');
  $id_b = $flow_b->getAttribute('id');
  $id_c = $flow_c->getAttribute('id');

  // Rotate: create -> contact -> save -> whatever came after the numbering.
  $flow_a->setAttribute('sourceRef', $job['create']);
  $flow_a->setAttribute('targetRef', $job['contact']);
  $flow_b->setAttribute('sourceRef', $job['contact']);
  $flow_b->setAttribute('targetRef', $job['save']);
  $flow_c->setAttribute('sourceRef', $job['save']);
  $flow_c->setAttribute('targetRef', $job['after']);

  // The incoming and outgoing children are what the visual editor reads to know
  // which line belongs to which box. They have to say the same as the flows.
  $rewire = [
    $job['create'] => ['outgoing' => $id_a],
    $job['contact'] => ['incoming' => $id_a, 'outgoing' => $id_b],
    $job['save'] => ['incoming' => $id_b, 'outgoing' => $id_c],
    $job['after'] => ['incoming' => $id_c],
  ];
  foreach ($rewire as $activity_id => $ends) {
    $activity = $by_id($activity_id);
    if (!$activity) {
      echo "  Falta el paso {$activity_id}.\n";
      $fallos++;
      continue 2;
    }
    foreach ($ends as $direction => $flow_id) {
      $existing = $xpath->query(sprintf('*[local-name()="%s"]', $direction), $activity);
      if ($existing->length) {
        $existing->item(0)->nodeValue = $flow_id;
      }
      else {
        $activity->appendChild($dom->createElement('bpmn2:' . $direction, $flow_id));
      }
    }
  }

  // Swap the two boxes on the canvas so the picture still reads left to right.
  $shape_save = $drawing_of($job['save']);
  $shape_contact = $drawing_of($job['contact']);
  if ($shape_save && $shape_contact) {
    $bounds_save = $xpath->query('*[local-name()="Bounds"]', $shape_save)->item(0);
    $bounds_contact = $xpath->query('*[local-name()="Bounds"]', $shape_contact)->item(0);
    if ($bounds_save && $bounds_contact) {
      $x = $bounds_save->getAttribute('x');
      $y = $bounds_save->getAttribute('y');
      $bounds_save->setAttribute('x', $bounds_contact->getAttribute('x'));
      $bounds_save->setAttribute('y', $bounds_contact->getAttribute('y'));
      $bounds_contact->setAttribute('x', $x);
      $bounds_contact->setAttribute('y', $y);
    }
  }

  // Out with the numbering steps, their lines, and everything drawn for them.
  foreach ($doomed_flows as $flow) {
    $drawn = $drawing_of($flow->getAttribute('id'));
    if ($drawn) {
      $drawn->parentNode->removeChild($drawn);
    }
    $flow->parentNode->removeChild($flow);
  }
  foreach ($chain as $activity_id) {
    $activity = $by_id($activity_id);
    if ($activity) {
      $activity->parentNode->removeChild($activity);
    }
    $drawn = $drawing_of($activity_id);
    if ($drawn) {
      $drawn->parentNode->removeChild($drawn);
    }
  }

  // Straighten the three surviving lines onto the boxes they now join. Purely
  // cosmetic: a line whose ends float in space still works, it just looks wrong
  // to whoever opens the editor next.
  foreach ([$id_a => [$job['create'], $job['contact']], $id_b => [$job['contact'], $job['save']], $id_c => [$job['save'], $job['after']]] as $flow_id => $ends) {
    $edge = $drawing_of($flow_id);
    if (!$edge) {
      continue;
    }
    $box = [];
    foreach ($ends as $activity_id) {
      $shape = $drawing_of($activity_id);
      $bounds = $shape ? $xpath->query('*[local-name()="Bounds"]', $shape)->item(0) : NULL;
      if (!$bounds) {
        continue 2;
      }
      $box[] = [
        'x' => (float) $bounds->getAttribute('x'),
        'y' => (float) $bounds->getAttribute('y'),
        'w' => (float) $bounds->getAttribute('width'),
        'h' => (float) $bounds->getAttribute('height'),
      ];
    }
    foreach ($xpath->query('*[local-name()="waypoint"]', $edge) as $waypoint) {
      $edge->removeChild($waypoint);
    }
    $points = [
      [$box[0]['x'] + $box[0]['w'], $box[0]['y'] + $box[0]['h'] / 2],
      [$box[1]['x'], $box[1]['y'] + $box[1]['h'] / 2],
    ];
    foreach ($points as $i => [$px, $py]) {
      $waypoint = $dom->createElement('di:waypoint');
      $waypoint->setAttribute('x', (string) (int) $px);
      $waypoint->setAttribute('y', (string) (int) $py);
      $edge->insertBefore($waypoint, $edge->firstChild && $i === 0 ? $edge->firstChild : NULL);
    }
  }

  // The save step now follows this one, so this one must not save by itself.
  // Two saves in a row is not wrong, only wasteful, but leaving it saying yes
  // would hide which step is the one that decides the name.
  $contact = $by_id($job['contact']);
  $save_field = $xpath->query('*[local-name()="extensionElements"]/*[local-name()="field"][@name="save_entity"]/*[local-name()="string"]', $contact);
  if ($save_field->length) {
    $save_field->item(0)->nodeValue = 'no';
  }

  $new_xml = $dom->saveXML();

  $modeller = $modeller_services->getModeller('bpmn_io');
  if (!$modeller) {
    echo "  No hay modeller bpmn_io.\n";
    $fallos++;
    continue;
  }
  $modeller->save($new_xml);
  if ($modeller->hasError()) {
    echo "  ECA no ha aceptado el dibujo.\n";
    $fallos++;
    continue;
  }

  echo "  Dibujo operado y fichero ejecutable regenerado.\n";
}

echo "\n";
echo $fallos ? "Han quedado {$fallos} procesos sin tocar.\n" : "Los dos procesos operados.\n";
echo "Copia de lo que habia antes en scripts/temp-eca-antes/.\n";
