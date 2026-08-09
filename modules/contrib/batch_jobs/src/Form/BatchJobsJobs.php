<?php

namespace Drupal\batch_jobs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\batch_jobs\Job;

/**
 * Batch jobs form.
 */
class BatchJobsJobs extends FormBase {

  /**
   * The user account.
   *
   * @var Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Class constructor.
   */
  public function __construct(AccountInterface $account, Connection $connection) {
    $this->account = $account;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'batch_jobs_jobs_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = $this->account;
    $header = [
      ['data' => t('Title'), 'field' => 'title'],
      ['data' => t('User'), 'field' => 'uid'],
      t('Total'),
      t('Started'),
      t('Completed'),
      t('Errors'),
      t('Status'),
      t('Action'),
    ];
    $sql = $this->connection->select('batch_jobs', 'jobs')
      ->fields('jobs', ['bid', 'title', 'uid'])
      ->extend('Drupal\Core\Database\Query\TableSortExtender')
      ->orderByHeader($header);
    if ($user->id() == 1) {
      $jobs = $sql->execute();
    }
    else {
      $jobs = $sql->condition('jobs.uid', [0, $user->id()], 'IN')
        ->execute();
    }

    $form['jobs'] = [
      '#type' => 'table',
      '#attributes' => ['class' => ['batch-jobs']],
      '#header' => $header,
      '#empty' => t('There are no jobs yet.'),
    ];

    foreach ($jobs as $batch_job) {
      $job = new Job($batch_job->bid);
      $token = $job->getToken($job->bid);
      $form['jobs'][$job->bid]['title'] = [
        '#markup' => '<a href="/batch-jobs/' . $job->bid . '">' . $job->title . '</a>',
      ];
      $form['jobs'][$job->bid]['user'] = [
        '#markup' => $job->getUser(),
      ];
      $total = $job->total();
      $form['jobs'][$job->bid]['total'] = [
        '#prefix' => '<div class="align-right">',
        '#markup' => $total,
        '#postfix' => '</div>',
      ];
      $form['jobs'][$job->bid]['started'] = [
        '#prefix' => '<div class="align-right">',
        '#markup' => $job->started(),
        '#postfix' => '</div>',
      ];
      $completed = $job->completed();
      $form['jobs'][$job->bid]['completed'] = [
        '#prefix' => '<div class="align-right">',
        '#markup' => $completed,
        '#postfix' => '</div>',
      ];
      $form['jobs'][$job->bid]['errors'] = [
        '#prefix' => '<div class="align-right">',
        '#markup' => $job->errors(),
        '#postfix' => '</div>',
      ];
      if ($completed == $total) {
        if ($job->status) {
          $form['jobs'][$job->bid]['status'] = [
            '#prefix' => '<div class="align-center">',
            '#markup' => t('Completed'),
            '#postfix' => '</div>',
          ];
        }
        else {
          $form['jobs'][$job->bid]['status'] = [
            '#prefix' => '<div class="align-center">',
            '#type' => 'button',
            '#value' => t('Run finish tasks'),
            '#postfix' => '</div>',
            '#attributes' => ['token' => [$token]],
          ];
        }
      }
      else {
        $form['jobs'][$job->bid]['status'] = [
          '#prefix' => '<div class="align-center">',
          '#type' => 'button',
          '#value' => t('Run'),
          '#postfix' => '</div>',
          '#attributes' => ['token' => [$token]],
        ];
      }
      $form['jobs'][$job->bid]['operations'] = [
        '#prefix' => '<div class="align-center">',
        '#type' => 'button',
        '#value' => t('Delete'),
        '#postfix' => '</div>',
        '#attributes' => ['token' => [$token]],
      ];
    }

    $form['#attached']['library'][] = 'batch_jobs/batch_jobs';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Construct a link.
   *
   * @param string $text
   *   Text to be displayed for link.
   * @param string $url
   *   Url for the link.
   * @param array $classes
   *   Classes for the link.
   */
  private function link($text, $url, array $classes = []) {
    $attributes = '';
    if (count($classes) > 0) {
      $attributes .= 'class="' . implode(' ', $classes) . '" ';
    }
    $attributes .= 'href="' . $url . '"';
    return '<a ' . $attributes . '>' . $text . '</a>';
  }

}
