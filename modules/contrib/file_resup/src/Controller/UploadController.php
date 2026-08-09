<?php

namespace Drupal\file_resup\Controller;

use Drupal\Core\Database\Database;
use Drupal\file_resup\Entity\FileResup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\file_resup\Event\BuildFormEvent;
use Drupal\Core\File\HtaccessWriterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\file\Upload\FileValidationException;
use Drupal\file_resup\Form\FileWidgetFormAlter;
use Drupal\Core\File\Exception\FileWriteException;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\File\Exception\DirectoryNotReadyException;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Consolidation\OutputFormatters\Exception\InvalidFormatException;

/**
 * Returns responses for File Resumable Upload routes.
 */
class UploadController extends ControllerBase {

  protected FileSystemInterface $fileSystem;
  protected HtaccessWriterInterface $htaccessWriter;
  protected StreamWrapperManagerInterface $streamWrapperManager;
  protected EventDispatcherInterface $eventDispatcher;

  public function __construct(FileSystemInterface $file_system, StreamWrapperManagerInterface $stream_wrapper_manager, HtaccessWriterInterface $htaccess_writer, EventDispatcherInterface $event_dispatcher) {
    $this->fileSystem = $file_system;
    $this->htaccessWriter = $htaccess_writer;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('stream_wrapper_manager'),
      $container->get('file.htaccess_writer'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Builds the response.
   */
  public function build(Request $request) {
    $method = $request->getMethod();
    $parameters = $method === 'GET' ? $request->query->all() : $request->request->all();

    // Get a valid upload ID.
    if (empty($parameters['resup_file_id']) || !($upload_id = FileWidgetFormAlter::uploadIdFromFileId($parameters['resup_file_id']))) {
      throw new \InvalidArgumentException('Missing resup_file_id.');
    }
    $upload_id = urldecode($upload_id);

    // On method GET...
    if ($method === 'GET') {
      // Attempt to find a record for the upload.
      $upload = FileResup::load($upload_id);
      // If found, return how many chunks were uploaded so far.
      if ($upload) {
        return $this->plainOutput($upload->uploaded_chunks->value);
      }

      $form = $this->eventDispatcher->dispatch(new BuildFormEvent($parameters['form_type'], $parameters))->getForm();

      $element_parents = explode(',', $parameters['form_parents']);
      if (is_numeric($element_parents[array_key_last($element_parents)])) {
        // Let's use 0 for the delta since we are building the form from scratch.
        $element_parents[max(array_keys($element_parents))] = 0;
      }
      $element = NestedArray::getValue($form, $element_parents);

      $filename = $parameters['resup_file_name'];
      $filesize = $parameters['resup_file_size'];

      // Validate the file name length.
      if (strlen($filename) > 240) {
        throw new FileValidationException('File name too long.', $filename, []);
      }

      // Validate the file extension.
      $file_extensions = $element['#upload_validators']['file_validate_extensions'][0] ?? $element['#upload_validators']['FileExtension']['extensions'];
      if (!empty($file_extensions)) {
        $regex = '/\.(?:' . preg_replace('/ +/', '|', preg_quote($file_extensions)) . ')$/i';
        if (!preg_match($regex, $filename)) {
          throw new FileValidationException('Unsupported file type', $filename, []);
        }
      }

      // Validate the file size.
      $file_size = $element['#upload_validators']['file_validate_size'][0] ?? $element['#upload_validators']['FileSizeLimit']['fileLimit'];
      if (!preg_match('`^[1-9]\d*$`', $filesize) || $filesize > $file_size) {
        throw new FileValidationException('File too large.', $filename, []);
      }

      // Retrieve the upload location scheme from the form element.
      $scheme = StreamWrapperManager::getScheme($element['#upload_location']);
      if (!$scheme || !$this->streamWrapperManager->isValidScheme($scheme)) {
        throw new FileValidationException('Invalid upload location', $element['##upload_location'], []);
      }

      // Prepare the file_resup_temporary private directory.
      $directory = "$scheme://" . FILE_RESUP_TEMPORARY;
      if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
        throw new DirectoryNotReadyException("Can't create file_resup's temporary directory.");
      }
      $this->htaccessWriter->write($directory, TRUE);

      // Insert a new upload record.
      $upload = FileResup::create([
        'upload_id' => $upload_id,
        'filename' => $filename,
        'filesize' => $filesize,
        'scheme' => $scheme,
        'changed' => time(),
      ]);
      $upload->save();

      // No upload file should exist at this point.
      $this->fileSystem->delete(FileWidgetFormAlter::getUploadUri($upload));

      // Return 0 as the number of uploaded chunks.
      return $this->plainOutput('0');
    }
    // On method POST...
    // Ensure we have a valid uploaded file.
    $file = $request->files->get('resup_chunk');
    if (empty($file)) {
      throw new FileValidationException('Missing file upload chunk.', $upload_id, []);
    }

    if ($file->getError() != UPLOAD_ERR_OK || !is_uploaded_file($file->getPathname()) || !$file->getSize() || $file->getSize() > file_resup_chunksize()) {
      throw new FileWriteException('File Resup failed to upload.');
    }

    // Validate the format of the chunk number.
    if (empty($parameters['resup_chunk_number']) || !preg_match('`^[1-9]\d*$`', $parameters['resup_chunk_number'])) {
      throw new InvalidFormatException('Invalid chunk number format', $parameters['resup_chunk_number'], 'integer');
    }
    $chunk_number = (int) $parameters['resup_chunk_number'];

    // Get the upload record.
    $upload = FileResup::load($upload_id);
    // If no record was found, return nothing.
    if (!$upload) {
      throw new MissingDataException('File Res upload not found.');
    }

    // Validate the chunk number.
    if ($chunk_number > ceil($upload->filesize->value / file_resup_chunksize())) {
      throw new \InvalidArgumentException('Invalid chunk number');
    }

    // If we were given an unexpected chunk number, return what we expected.
    if ($chunk_number != $upload->uploaded_chunks->value + 1) {
      return $this->plainOutput($upload->uploaded_chunks->value);
    }

    // Open the upload file.
    $fp = @fopen(FileWidgetFormAlter::getUploadUri($upload), 'ab');
    if (!$fp) {
      throw new FileWriteException("Couldn't open ". FileWidgetFormAlter::getUploadUri($upload) . " for writing.");
    }

    // Acquire an exclusive lock.
    if (!flock($fp, LOCK_EX)) {
      fclose($fp);
      throw new LockAcquiringException("Couldn't acquire an exclusive lock for ". FileWidgetFormAlter::getUploadUri($upload) . ".");
    }

    // Update the record and append the chunk.
    $transaction = Database::getConnection()->startTransaction();
    try {
      $upload->set('uploaded_chunks', $chunk_number);
      $upload->set('changed', time());
      $upload->save();
      if (($contents = file_get_contents($file->getPathname())) === FALSE || !fwrite($fp, $contents)) {
        throw new \Exception();
      }
    }
    catch (\Exception $e) {
      $transaction->rollback();
      flock($fp, LOCK_UN);
      fclose($fp);
      throw new FileWriteException('Failed to write chunk.');
    }

    // Commit the transaction.
    unset($transaction);

    // Flush the output then unlock and close the file.
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    // Return the updated number of uploaded chunks.
    return $this->plainOutput($chunk_number);
  }

  private function plainOutput($text = '') {
    return new Response($text, 200, ['Content-Type', 'text/plain']);
  }

}
