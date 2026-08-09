<?php

namespace Drupal\Tests\batch_jobs\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\batch_jobs\Job;

/**
 * Batch Jobs test.
 *
 * @group batch_jobs
 */
class BatchJobsTest extends BrowserTestBase {

  /**
   * Theme.
   *
   * @var string
   */
  protected $defaultTheme = 'classy';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'batch_jobs', 'batch_jobs_example'];

  /**
   * Batch Jobs test.
   */
  public function testBatchJobs() {
    $admin_user = $this->drupalCreateUser(['access batch jobs']);
    $this->drupalLogin($admin_user);

    // Create batch job.
    $batch = batch_jobs_example_create_job(10);
    $this->drupalGet('admin/reports/batch-jobs');
    $this->assertSession()->pageTextContains('Create node');
    $this->assertSession()->responseContains('value="run"');
    
    // Run batch job.
    $this->runBatchJobs($batch->bid);
    $this->drupalGet('admin/reports/batch-jobs');
    $this->assertSession()->responseContains('value="Run finish tasks"');

    // Run finish task.
    $job = new Job($batch->bid);
    $job->finish();
    $this->drupalGet('admin/reports/batch-jobs');
    $this->assertSession()->pageTextContains('Completed');
  }

  /**
   * Run batch job.
   *
   * Alas, since Javascript does not work we have to run the tasks here.
   */
  private function runBatchJobs($bid) {
    $job = new Job($bid);
    $job_data = $job->getData();
    do {
      $tasks = batch_jobs_get_tasks($job->bid);
      if (count($tasks) == 0) {
        break;
      }
      foreach ($tasks as $task) {
        $task->startTask();
        // Array merge allows task parameters to override batch parameters.
        $task_params = array_merge($job_data, $task->getData());
        $message = [];
        $status = FALSE;
        foreach ($task->getCallbacks() as $callback) {
          $result = call_user_func($callback, $task_params);
          $status = $result->status;
          if (!isset($result->status) || !$result->status) {
            break;
          }
          if (isset($result->message)) {
            $message += $result->message;
          }
        }
        $task->endTask($status, $message);
      }
    } while (1);
  }

}
