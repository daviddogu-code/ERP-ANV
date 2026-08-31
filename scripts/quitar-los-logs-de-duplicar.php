<?php

/**
 * @file
 * Quita los cajones Log de depuracion de los procesos de duplicar.
 *
 *   php scripts/quitar-los-logs-de-duplicar.php
 *
 * No arranca Drupal. Reescribe los YAML en config/sync. El proceso que corre
 * es eca.eca; el dibujo es eca.model. Hay que tocar los dos o la proxima vez
 * que alguien abra el modelador devolveria los Logs.
 *
 * Un clic de duplicar color dispara el proceso del color y, al guardar las
 * fichas nuevas, los de insertar/actualizar talla y color, y el que pone las
 * referencias en el producto y el de insertar color. Por eso se limpian esos
 * ocho, no solo Duplicate color.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use Drupal\Component\Serialization\Yaml;

const PROCESOS = [
  'process_fc3hjta', // TEC Product: Duplicate color
  'process_u1bemhe', // TEC Product: Duplicate size
  'process_llpx4tp', // TEC Product: Duplicate product
  'process_hkreor6', // TEC Color variation: Set data values
  'process_ocmwa9c', // TEC Product: Update Size entity
  'process_qlf96jn', // TEC Product: Insert Size entity
  'process_dxgyftk', // TEC Product: Set references on sub-entities
  'process_b20b2si', // TEC Product: Insert Color entity
];

$raiz = dirname(__DIR__) . '/config/sync';

foreach (PROCESOS as $id) {
  $ecaFile = $raiz . '/eca.eca.' . $id . '.yml';
  $modelFile = $raiz . '/eca.model.' . $id . '.yml';
  if (!is_file($ecaFile) || !is_file($modelFile)) {
    fwrite(STDERR, "Falta $id\n");
    exit(1);
  }

  $eca = Yaml::decode(file_get_contents($ecaFile));
  $n = strip_logs_from_eca($eca);
  if ($n === 0) {
    printf("  %s  ya limpio, version %s\n", $id, $eca['version'] ?? '?');
    continue;
  }
  bump_version($eca);
  file_put_contents($ecaFile, Yaml::encode($eca));

  $model = Yaml::decode(file_get_contents($modelFile));
  $xml = $model['modeldata'] ?? '';
  if (!is_string($xml) || $xml === '') {
    fwrite(STDERR, "$id: modeldata vacio\n");
    exit(1);
  }
  $model['modeldata'] = strip_logs_from_bpmn($xml, $id);
  if (isset($eca['version'])) {
    $model['modeldata'] = preg_replace(
      '/camunda:versionTag="[^"]*"/',
      'camunda:versionTag="' . $eca['version'] . '"',
      $model['modeldata'],
      1
    );
  }
  file_put_contents($modelFile, Yaml::encode($model));

  printf("  %s  %d logs fuera, version %s\n", $id, $n, $eca['version'] ?? '?');
}

echo "\nListo. Importar con drush y probar el duplicado.\n";

/**
 * Quita eca_write_log_message y reengancha a quien iba detras.
 */
function strip_logs_from_eca(array &$config): int {
  $logs = [];
  foreach ($config['actions'] ?? [] as $id => $action) {
    if (($action['plugin'] ?? '') === 'eca_write_log_message') {
      $logs[$id] = $action['successors'] ?? [];
    }
  }
  if (!$logs) {
    return 0;
  }

  $resolve = function (array $successors) use (&$resolve, $logs): array {
    $out = [];
    foreach ($successors as $s) {
      $sid = $s['id'] ?? '';
      $cond = $s['condition'] ?? '';
      if (!isset($logs[$sid])) {
        $out[] = $s;
        continue;
      }
      $inner = $resolve($logs[$sid]);
      foreach ($inner as $i) {
        $out[] = [
          'id' => $i['id'],
          'condition' => $cond !== '' ? $cond : ($i['condition'] ?? ''),
        ];
      }
    }
    return $out;
  };

  foreach (['events', 'gateways', 'actions'] as $section) {
    if (!isset($config[$section]) || !is_array($config[$section])) {
      continue;
    }
    foreach ($config[$section] as $id => &$node) {
      if (isset($node['successors']) && is_array($node['successors'])) {
        $node['successors'] = $resolve($node['successors']);
      }
    }
    unset($node);
  }

  foreach (array_keys($logs) as $id) {
    unset($config['actions'][$id]);
  }

  $quedaLog = FALSE;
  foreach ($config['actions'] ?? [] as $action) {
    if (($action['plugin'] ?? '') === 'eca_write_log_message') {
      $quedaLog = TRUE;
      break;
    }
  }
  if (!$quedaLog && isset($config['dependencies']['module'])) {
    $config['dependencies']['module'] = array_values(array_filter(
      $config['dependencies']['module'],
      static fn($m): bool => $m !== 'eca_log'
    ));
  }

  return count($logs);
}

function bump_version(array &$config): void {
  $v = (string) ($config['version'] ?? '1.0');
  if (preg_match('/^(\d+)\.(\d+)$/', $v, $m)) {
    $config['version'] = $m[1] . '.' . ((int) $m[2] + 1);
  }
}

/**
 * Quita las task Log del dibujo BPMN y reengancha los sequenceFlow.
 */
function strip_logs_from_bpmn(string $xml, string $id): string {
  $dom = new DOMDocument();
  $dom->preserveWhiteSpace = FALSE;
  $dom->formatOutput = TRUE;
  if (!@$dom->loadXML($xml)) {
    throw new RuntimeException("XML roto en $id");
  }
  $xpath = new DOMXPath($dom);
  $xpath->registerNamespace('bpmn2', 'http://www.omg.org/spec/BPMN/20100524/MODEL');
  $xpath->registerNamespace('bpmndi', 'http://www.omg.org/spec/BPMN/20100524/DI');
  $xpath->registerNamespace('camunda', 'http://camunda.org/schema/1.0/bpmn');

  $logIds = [];
  foreach ($xpath->query('//bpmn2:task') as $task) {
    /** @var \DOMElement $task */
    foreach ($xpath->query('.//camunda:property[@name="pluginid"]', $task) as $prop) {
      if ($prop->getAttribute('value') === 'eca_write_log_message') {
        $logIds[$task->getAttribute('id')] = $task;
      }
    }
  }
  if (!$logIds) {
    return $xml;
  }

  $outgoingTargets = [];
  foreach ($xpath->query('//bpmn2:sequenceFlow') as $flow) {
    /** @var \DOMElement $flow */
    $outgoingTargets[$flow->getAttribute('sourceRef')][] = $flow;
  }

  $resolveTargets = function (string $from) use (&$resolveTargets, $outgoingTargets, $logIds): array {
    $targets = [];
    foreach ($outgoingTargets[$from] ?? [] as $flow) {
      $t = $flow->getAttribute('targetRef');
      if (isset($logIds[$t])) {
        foreach ($resolveTargets($t) as $tt) {
          $targets[] = $tt;
        }
      }
      else {
        $targets[] = $t;
      }
    }
    return array_values(array_unique($targets));
  };

  $deleteFlowIds = [];
  foreach ($xpath->query('//bpmn2:sequenceFlow') as $flow) {
    /** @var \DOMElement $flow */
    $src = $flow->getAttribute('sourceRef');
    $tgt = $flow->getAttribute('targetRef');
    if (isset($logIds[$src])) {
      $deleteFlowIds[$flow->getAttribute('id')] = $flow;
      continue;
    }
    if (!isset($logIds[$tgt])) {
      continue;
    }
    $next = $resolveTargets($tgt);
    if (!$next) {
      $deleteFlowIds[$flow->getAttribute('id')] = $flow;
      continue;
    }
    $flow->setAttribute('targetRef', $next[0]);
    for ($i = 1; $i < count($next); $i++) {
      $clone = $flow->cloneNode(TRUE);
      $clone->setAttribute('id', $flow->getAttribute('id') . '_r' . $i);
      $clone->setAttribute('targetRef', $next[$i]);
      $flow->parentNode->insertBefore($clone, $flow->nextSibling);
    }
  }

  foreach ($deleteFlowIds as $flow) {
    $flow->parentNode->removeChild($flow);
  }
  foreach ($logIds as $task) {
    $task->parentNode->removeChild($task);
  }

  // Formas e hilos del dibujo que apuntaban a lo borrado.
  $gone = array_merge(array_keys($logIds), array_keys($deleteFlowIds));
  foreach ($xpath->query('//bpmndi:BPMNShape|//bpmndi:BPMNEdge') as $di) {
    /** @var \DOMElement $di */
    $el = $di->getAttribute('bpmnElement');
    if (in_array($el, $gone, TRUE)) {
      $di->parentNode->removeChild($di);
    }
  }

  // Recalcular incoming/outgoing de cada nodo BPMN.
  rebuild_bpmn_wires($dom);

  $out = $dom->saveXML();
  if ($out === FALSE) {
    throw new RuntimeException("No se pudo serializar $id");
  }
  // El YAML original traia el XML sin declaracion; saveXML la anade.
  return $out;
}

function rebuild_bpmn_wires(DOMDocument $dom): void {
  $xpath = new DOMXPath($dom);
  $xpath->registerNamespace('bpmn2', 'http://www.omg.org/spec/BPMN/20100524/MODEL');

  $incoming = [];
  $outgoing = [];
  foreach ($xpath->query('//bpmn2:sequenceFlow') as $flow) {
    /** @var \DOMElement $flow */
    $fid = $flow->getAttribute('id');
    $outgoing[$flow->getAttribute('sourceRef')][] = $fid;
    $incoming[$flow->getAttribute('targetRef')][] = $fid;
  }

  foreach ($xpath->query('//bpmn2:*[@id]') as $node) {
    /** @var \DOMElement $node */
    if ($node->localName === 'sequenceFlow' || $node->localName === 'definitions') {
      continue;
    }
    $nid = $node->getAttribute('id');
    foreach (iterator_to_array($xpath->query('./bpmn2:incoming|./bpmn2:outgoing', $node)) as $child) {
      $node->removeChild($child);
    }
    foreach ($incoming[$nid] ?? [] as $fid) {
      $el = $dom->createElementNS('http://www.omg.org/spec/BPMN/20100524/MODEL', 'bpmn2:incoming');
      $el->textContent = $fid;
      $node->appendChild($el);
    }
    foreach ($outgoing[$nid] ?? [] as $fid) {
      $el = $dom->createElementNS('http://www.omg.org/spec/BPMN/20100524/MODEL', 'bpmn2:outgoing');
      $el->textContent = $fid;
      $node->appendChild($el);
    }
  }
}
