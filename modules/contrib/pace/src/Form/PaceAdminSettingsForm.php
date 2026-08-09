<?php

/**
 * @file
 * Contains \Drupal\pace\Form\PaceAdminSettingsForm.
 */

namespace Drupal\pace\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements settings form.
 */
class PaceAdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pace_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['pace.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('pace.settings');

    $form['pace_theme'] = array(
        '#title' => t('Select the theme that PACE should use'),
        '#description' => t('Pace comes with a lot of themes for the progress loader. Please select the one that you prefer. You can see them all here: http://github.hubspot.com/pace/docs/welcome/'),
        '#type' => 'radios',
        '#options' => array(
          'minimal' => 'minimal',
          'barber-shop' => 'barber',
          'big-counter' => 'big counter',
          'bounce' => 'bounce',
          'center-atom' => 'center atom',
          'center-circle' => 'center circle',
          'center-radar' => 'center radar',
          'center-simple' => 'center simple',
          'corner-indicator' => 'corner indicator',
          'fill-left' => 'fill left',
          'flash' => 'flash',
          'flat-top' => 'flat top',
          'loading-bar' => 'loading bar',
          'mac-osx' => 'mac osx',
          'material' => 'material',
        ),
        '#default_value' => $config->get('pace_theme') ?: 'minimal',
    );

    $form['pace_color'] = [
      '#title' => t('Select the color of the theme'),
      '#type' => 'radios',
      '#options' => [
        'black' => 'black',
        'blue' => 'blue',
        'green' => 'green',
        'orange' => 'orange',
        'pink' => 'pink',
        'purple' => 'purple',
        'red' => 'red',
        'silver' => 'silver',
        'white' => 'white',
        'yellow' => 'yellow',
      ],
        '#default_value' => $config->get('pace_color') ?: 'blue',
    ];

    $form['pace_load_on_admin_enabled'] = array(
        '#title' => t('Load in administration pages.'),
        '#description' => t('PACE is disabled by default on administration pages. Check to enable'),
        '#type' => 'checkbox',
        '#default_value' => $config->get('pace_load_on_admin_enabled') ?: FALSE,
    );

    $form['pace_use_unminified_js'] = [
      '#title' => t('Use unminified JS'),
      '#description' => t('Use unminified JS, helpful for debugging, development or to apply custom patches to PACE'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('pace_use_unminified_js') ?: FALSE,
    ];

    $form['color_notes'] = [
      '#type' => 'markup',
      '#markup' => t('<legend><strong>PACE Styling</strong></legend>To style PACE add a style tag like:<br><code>.pace .pace-progress { background: red; }</code><br> in your html.html.twig file inside the head tag.<br>It doesn\'t work for all PACE themes.<br>Setting the color from the UI is disabled because of <a href="https://drupal.stackexchange.com/questions/212376/attach-dynamic-inline-css-to-head" target="_blank">this</a>.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('pace.settings')
      ->set('pace_theme', $form_state->getValue('pace_theme'))
      ->set('pace_color', $form_state->getValue('pace_color'))
      ->set('pace_load_on_admin_enabled', $form_state->getValue('pace_load_on_admin_enabled'))
      ->set('pace_use_unminified_js', $form_state->getValue('pace_use_unminified_js'))
      ->save();
  }


}
