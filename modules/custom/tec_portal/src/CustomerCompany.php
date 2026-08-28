<?php

namespace Drupal\tec_portal;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Which company a portal login may see.
 *
 * A Drupal user is not a customer. The CRM already has the two things that
 * are: the person, and the organisation they work at. This class is the
 * missing join — user → person → company — and the questions the access
 * hook asks of it.
 *
 * Factory accounts (administrator, executive, manager, supervisor) are left
 * alone. Mixing those with the Customer role is a misconfiguration; this
 * class treats the mix as factory so the shop floor does not lock itself
 * out. Do not assign Customer to staff.
 */
final class CustomerCompany {

  public const ROLE = 'tec_customer';

  public const PERSON_FIELD = 'field_tec_contact_person';

  public const WORKS_AT_FIELD = 'field_tec_works_at';

  public const BRANDS_FIELD = 'field_tec_brands';

  public const ORDER_CUSTOMER_FIELD = 'field_tec_customer';

  public const PRODUCT_BRAND_FIELD = 'field_tec_brand';

  /**
   * Roles that run the factory ERP. Customer must never be one of these.
   */
  public const FACTORY_ROLES = [
    'administrator',
    'tec_executive',
    'tec_manager',
    'tec_supervisor',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Whether this account works the factory side of the ERP.
   */
  public function isFactory(AccountInterface $account): bool {
    if ((int) $account->id() === 1) {
      return TRUE;
    }
    foreach (self::FACTORY_ROLES as $role) {
      if ($account->hasRole($role)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Whether the portal lock applies to this account.
   *
   * True only for Customer with no factory role. An account with neither
   * is not a portal user and is not granted the Customer view permissions,
   * so ECK already refuses the entities. The lock still forbids if they
   * somehow get a permission later.
   */
  public function isPortalCustomer(AccountInterface $account): bool {
    return $account->isAuthenticated()
      && $account->hasRole(self::ROLE)
      && !$this->isFactory($account);
  }

  /**
   * The CRM person this login is tied to, or NULL.
   */
  public function person(AccountInterface $account): ?EntityInterface {
    $user = $this->user($account);
    if (!$user || !$user->hasField(self::PERSON_FIELD) || $user->get(self::PERSON_FIELD)->isEmpty()) {
      return NULL;
    }
    $person = $user->get(self::PERSON_FIELD)->entity;
    return $person && $person->bundle() === 'tec_contact_person' ? $person : NULL;
  }

  /**
   * The organisation id this login may see, or NULL if nothing is wired.
   */
  public function id(AccountInterface $account): ?int {
    $person = $this->person($account);
    if (!$person || !$person->hasField(self::WORKS_AT_FIELD) || $person->get(self::WORKS_AT_FIELD)->isEmpty()) {
      return NULL;
    }
    $company = $person->get(self::WORKS_AT_FIELD)->entity;
    if (!$company || $company->bundle() !== 'tec_contact_organization') {
      return NULL;
    }
    return (int) $company->id();
  }

  /**
   * The organisation entity, or NULL.
   */
  public function company(AccountInterface $account): ?EntityInterface {
    $id = $this->id($account);
    if (!$id) {
      return NULL;
    }
    $company = $this->entityTypeManager->getStorage('tec_crm')->load($id);
    return $company && $company->bundle() === 'tec_contact_organization' ? $company : NULL;
  }

  /**
   * Brand term ids this company buys, in the order stored on the card.
   *
   * @return int[]
   */
  public function brandIds(AccountInterface $account): array {
    $company = $this->company($account);
    if (!$company || !$company->hasField(self::BRANDS_FIELD)) {
      return [];
    }
    $ids = [];
    foreach ($company->get(self::BRANDS_FIELD) as $item) {
      if (!$item->isEmpty() && $item->target_id) {
        $ids[] = (int) $item->target_id;
      }
    }
    return $ids;
  }

  /**
   * Whether this account's company is one of the ids given.
   *
   * @param int[] $company_ids
   */
  public function ownsOneOf(AccountInterface $account, array $company_ids): bool {
    $mine = $this->id($account);
    if ($mine === NULL) {
      return FALSE;
    }
    foreach ($company_ids as $id) {
      if ((int) $id === $mine) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Company ids referenced from an order's customer field.
   *
   * @return int[]
   */
  public function orderCompanyIds(EntityInterface $order): array {
    if (!$order->hasField(self::ORDER_CUSTOMER_FIELD)) {
      return [];
    }
    $ids = [];
    foreach ($order->get(self::ORDER_CUSTOMER_FIELD) as $item) {
      if (!$item->isEmpty() && $item->target_id) {
        $ids[] = (int) $item->target_id;
      }
    }
    return $ids;
  }

  /**
   * The product entity a colour or size variation belongs to, or NULL.
   */
  public function parentProduct(EntityInterface $entity): ?EntityInterface {
    if ($entity->bundle() === 'tec_product') {
      return $entity;
    }
    if ($entity->hasField('field_tec_product') && !$entity->get('field_tec_product')->isEmpty()) {
      $id = (int) $entity->get('field_tec_product')->target_id;
      $product = $id ? $this->entityTypeManager->getStorage('tec_product')->load($id) : NULL;
      return $product && $product->bundle() === 'tec_product' ? $product : NULL;
    }
    return NULL;
  }

  /**
   * Brand term id on a product (or on a variation, walking up), or 0.
   */
  public function productBrandId(EntityInterface $entity): int {
    $product = $this->parentProduct($entity);
    if (!$product || !$product->hasField(self::PRODUCT_BRAND_FIELD) || $product->get(self::PRODUCT_BRAND_FIELD)->isEmpty()) {
      return 0;
    }
    return (int) $product->get(self::PRODUCT_BRAND_FIELD)->target_id;
  }

  /**
   * Access result with the cache tags of the user, person and company.
   *
   * Callers add forbidden() or allowed() / neutral() on top.
   */
  public function cache(AccountInterface $account): AccessResultInterface {
    $result = AccessResult::allowed()->addCacheContexts(['user.roles', 'user']);
    $user = $this->user($account);
    if ($user) {
      $result->addCacheableDependency($user);
    }
    $person = $this->person($account);
    if ($person) {
      $result->addCacheableDependency($person);
    }
    $company = $this->company($account);
    if ($company) {
      $result->addCacheableDependency($company);
    }
    return $result;
  }

  /**
   * Whether an entity query is honouring entity access.
   *
   * Factory code that calls accessCheck(FALSE) must not be rewritten: those
   * queries are the shop floor counting every order. The lock only belongs
   * on queries that already asked to respect access.
   */
  public static function queryChecksAccess(QueryInterface $query): bool {
    $class = new \ReflectionClass($query);
    while ($class && !$class->hasProperty('accessCheck')) {
      $class = $class->getParentClass();
    }
    if (!$class) {
      return TRUE;
    }
    $property = $class->getProperty('accessCheck');
    $property->setAccessible(TRUE);
    return (bool) $property->getValue($query);
  }

  /**
   * The user entity for this account, or NULL.
   */
  private function user(AccountInterface $account): ?UserInterface {
    if ($account instanceof UserInterface) {
      return $account;
    }
    $id = (int) $account->id();
    if ($id < 1) {
      return NULL;
    }
    $user = $this->entityTypeManager->getStorage('user')->load($id);
    return $user instanceof UserInterface ? $user : NULL;
  }

}
