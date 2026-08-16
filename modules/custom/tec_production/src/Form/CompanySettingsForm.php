<?php

namespace Drupal\tec_production\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tec_production\EventSubscriber\QueueTileRedirectSubscriber;
use Drupal\tec_production\Vat;

/**
 * The handful of settings that belong to the company and not to one screen.
 *
 * Two things live here so far. The VAT rate, which is one number for the whole
 * company and was previously only reachable from a command line. And the five
 * home page icons, whose pairing with the screens they open was in the same
 * place and just as unreachable.
 *
 * The capacity settings deliberately stayed on the queue screen. They are read
 * next to the figures they move, and taking them away from those figures to put
 * them behind a menu would be tidier and worse.
 */
class CompanySettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['tec_production.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tec_production_company_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('tec_production.settings');

    $form['vat'] = [
      '#type' => 'details',
      '#title' => $this->t('VAT'),
      '#open' => TRUE,
    ];
    $form['vat']['vat_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Standard rate'),
      '#field_suffix' => '%',
      '#default_value' => Vat::standardRate(),
      '#min' => 0,
      '#max' => 100,
      '#step' => 0.01,
      '#required' => TRUE,
      '#description' => $this->t('Thailand charges 7%. This is the rate, once, for the whole company; <em>which</em> suppliers charge it is a separate answer on each contact card, under VAT treatment. Changing it here affects orders raised from now on: an order keeps the rate it was raised under, so nothing already printed and sent moves.'),
    ];

    $form['tiles'] = [
      '#type' => 'details',
      '#title' => $this->t('Home page icons'),
      '#open' => FALSE,
      '#description' => $this->t('Each icon on the home page is an ordinary page, so that it sits on the grid with the rest. Opening one sends you straight to the screen it stands for, and this is where that pairing is set. You should only need this after rebuilding the home page.'),
    ];

    $nodes = \Drupal::entityTypeManager()->getStorage('node');
    $routes = \Drupal::service('router.route_provider');

    foreach (QueueTileRedirectSubscriber::TILES as $key => $route_name) {
      // Named after the screen's own title, so the two cannot end up disagreeing
      // about what a screen is called.
      try {
        $route = $routes->getRouteByName($route_name);
        $screen = $route->getDefault('_title');
        $path = $route->getPath();
      }
      catch (\Exception $e) {
        continue;
      }

      $nid = (int) $settings->get($key);
      $form['tiles'][$key] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'node',
        '#selection_settings' => ['target_bundles' => ['tec_landing_page']],
        '#title' => $this->t('Icon that opens @screen', ['@screen' => $screen]),
        '#description' => $this->t('Goes to @path.', ['@path' => $path]),
        '#default_value' => $nid > 0 ? $nodes->load($nid) : NULL,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $settings = $this->config('tec_production.settings');

    $settings->set('vat_rate', (float) $form_state->getValue('vat_rate'));

    // Cast to a plain integer. An empty box means no icon rather than node
    // zero, and both come out of the cast as 0, which is what the redirect
    // treats as "not set".
    foreach (array_keys(QueueTileRedirectSubscriber::TILES) as $key) {
      $settings->set($key, (int) $form_state->getValue($key));
    }

    $settings->save();
    parent::submitForm($form, $form_state);
  }

}
