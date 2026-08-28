<?php

namespace Drupal\tec_portal\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tec_portal\CustomerCompany;
use Drupal\views\Attribute\ViewsArgumentDefault;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views argument: the company of the logged-in portal user.
 *
 * Listings for the customer use this so the company id never appears in the
 * path. A view that forgets it and lists every sales order is still stopped
 * by the entity access hook; this is the belt.
 */
#[ViewsArgumentDefault(
  id: 'tec_portal_company',
  title: new TranslatableMarkup("Portal: current user's company"),
)]
class CustomerCompanyId extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected CustomerCompany $companies,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tec_portal.company'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    $id = $this->companies->id(\Drupal::currentUser());
    return $id ? (string) $id : '0';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $account = \Drupal::currentUser();
    $tags = ['user:' . $account->id()];
    $person = $this->companies->person($account);
    if ($person) {
      $tags = Cache::mergeTags($tags, $person->getCacheTags());
    }
    $company = $this->companies->company($account);
    if ($company) {
      $tags = Cache::mergeTags($tags, $company->getCacheTags());
    }
    return $tags;
  }

}
