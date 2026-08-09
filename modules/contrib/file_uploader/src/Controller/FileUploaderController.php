<?php

namespace Drupal\file_uploader\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides file uploader controller.
 */
class FileUploaderController extends ControllerBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    $instance->fileSystem = $container->get('file_system');
    return $instance;
  }

  /**
   * Check access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The users account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Returns the access result.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $file = $this->request->files->get('file');
    if (!$file instanceof UploadedFile || !$key = $this->request->query->get('key')) {
      return AccessResult::forbidden();
    }
    $element = unserialize($this->keyValue('file_uploader')->get($key), ['allowed_classes' => FALSE]);
    return AccessResult::allowedIf(isset($element['#name'], $element['#upload_location']));
  }

  /**
   * Save uploaded managed file.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function upload(Request $request): AjaxResponse {
    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
    $file = $request->files->get('file');
    $key = $request->query->get('key');
    $element = unserialize($this->keyValue('file_uploader')->get($key), ['allowed_classes' => FALSE]) + [
      '#upload_validators' => [],
    ];

    // Mimic managed file uploads with validators.
    $request->files->set('files', [$element['#name'] => $file]);
    $this->fileSystem->prepareDirectory($element['#upload_location'], FileSystemInterface::CREATE_DIRECTORY);
    $file = current(file_save_upload($element['#name'], $element['#upload_validators'], $element['#upload_location']));
    if ($file instanceof FileInterface) {
      return new AjaxResponse(['value' => $file->id()]);
    }

    $messages = $this->messenger()->messagesByType('error');
    $this->messenger()->deleteByType('error');
    return new AjaxResponse(implode(PHP_EOL, $messages), 400);
  }

}
