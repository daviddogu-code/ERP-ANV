<?php

namespace Drupal\batch_jobs_example\Commands;

use Drush\Commands\DrushCommands;
use Drupal\batch_jobs\BatchJob;
use Drupal\batch_jobs\Job;

/**
 * An example Drush commandfile for running a batch job.
 */
class BatchJobsExample extends DrushCommands {

  /**
   * Runs a batch job for creating nodes.
   *
   * @param int $number
   *   Number of nodes to be run.
   *
   * @command batch_jobs:example
   * @aliases bje
   * @usage batch_jobs:example 50
   *   Create and run a batch job.
   */
  public function example($number) {
    $batch = new BatchJob('Create nodes', 1);
    $batch_params = [
      'entity_type' => 'node',
    ];
    $batch->addBatchParams($batch_params);
    $batch_callbacks = [];
    $batch->addBatchCallbacks($batch_callbacks);
    for ($i = 0; $i < $number; $i++) {
      $title = 'Node ' . ($i + 1);
      $callbacks = [
        'batch_jobs_example_create_node',
      ];
      $params = [
        'values' => [
          'title' => $title,
          'type' => 'article',
        ],
      ];
      $batch->addTask($title, $callbacks, $params);
    }
    $this->output()->writeln('Created a batch job with ' . $number . ' tasks.');

    $job = new Job($batch->bid);
    $ran = $job->run();
    $this->output()->writeln('Ran ' . $ran . ' tasks');
    $job->finish();
    $this->output()->writeln('Created ' . $ran . ' nodes');
  }

}
