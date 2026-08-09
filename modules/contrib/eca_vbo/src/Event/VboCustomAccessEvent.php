<?php

namespace Drupal\eca_vbo\Event;

use Drupal\Core\Session\AccountInterface;
use Drupal\views\ViewExecutable;

/**
 * Dispatches for custom access check on a "eca_vbo_execute" action.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class VboCustomAccessEvent extends VboEventBase {

  /**
   * The account to check access for.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The executable view.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected ViewExecutable $view;

  /**
   * This flag stores whether access is granted or not.
   *
   * @var bool
   */
  public bool $accessGranted;

  /**
   * The action plugin definition, or NULL if not available.
   *
   * @var array|null
   */
  protected ?array $actionDefinition;

  /**
   * The selected action data, or NULL if not available.
   *
   * @var array|null
   */
  protected ?array $selectedActionData;

  /**
   * Constructs a new VboCustomAccessEvent object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   * @param \Drupal\views\ViewExecutable $view
   *   The executable view.
   * @param bool $access_granted
   *   The default value whether access is granted.
   * @param array|null $action_definition
   *   The action plugin definition, or NULL if not available.
   * @param array|null $selected_action_data
   *   The selected action data, or NULL if not available.
   */
  public function __construct(AccountInterface $account, ViewExecutable $view, bool $access_granted, ?array $action_definition = NULL, ?array $selected_action_data = NULL) {
    $this->account = $account;
    $this->view = $view;
    $this->accessGranted = $access_granted;
    $this->actionDefinition = $action_definition;
    $this->selectedActionData = $selected_action_data;
    if (isset($selected_action_data['operation_name'])) {
      $this->operationName = &$selected_action_data['operation_name'];
    }
    elseif (isset($action_definition['operation_name'])) {
      $this->operationName = &$action_definition['operation_name'];
    }
  }

  /**
   * Get the account to check access for.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The account.
   */
  public function getAccount(): AccountInterface {
    return $this->account;
  }

  /**
   * Get the executable view.
   *
   * @return \Drupal\views\ViewExecutable
   *   The executable view.
   */
  public function getView(): ViewExecutable {
    return $this->view;
  }

}
