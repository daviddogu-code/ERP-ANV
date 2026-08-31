<?php

namespace Drupal\tec_production\Form;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;
use Drupal\address\Element\Address;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\tec_production\Company;
use Drupal\tec_production\EventSubscriber\QueueTileRedirectSubscriber;
use Drupal\tec_production\Vat;

/**
 * Who we are, the VAT rate, and which home-page icon opens which screen.
 *
 * Letterhead used to be HTML pasted into a Views header. The rate and the
 * icon pairings lived in the same config object with no screen. This is
 * that screen. Capacity stays on the queue: those figures are read next
 * to the numbers they move.
 */
class CompanySettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [Company::CONFIG];
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
    $settings = $this->config(Company::CONFIG);

    $form['identity'] = [
      '#type' => 'details',
      '#title' => $this->t('Our company'),
      '#open' => TRUE,
      '#weight' => -30,
      '#description' => $this->t('Printed on proformas and purchase orders.'),
    ];
    $form['identity']['legal_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Legal name'),
      '#default_value' => Company::legalName(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['identity']['address'] = [
      '#type' => 'address',
      '#title' => $this->t('Address'),
      '#default_value' => Company::address(),
      '#field_overrides' => [
        AddressField::GIVEN_NAME => FieldOverride::HIDDEN,
        AddressField::ADDITIONAL_NAME => FieldOverride::HIDDEN,
        AddressField::FAMILY_NAME => FieldOverride::HIDDEN,
        AddressField::ORGANIZATION => FieldOverride::HIDDEN,
        AddressField::ADDRESS_LINE3 => FieldOverride::HIDDEN,
        AddressField::SORTING_CODE => FieldOverride::HIDDEN,
      ],
    ];
    $form['identity']['tax_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tax ID'),
      '#default_value' => Company::taxId(),
      '#maxlength' => 64,
    ];
    $form['identity']['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone'),
      '#default_value' => Company::phone(),
      '#maxlength' => 64,
    ];
    $form['identity']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => Company::email(),
    ];
    $form['identity']['website'] = [
      '#type' => 'url',
      '#title' => $this->t('Website'),
      '#default_value' => Company::website(),
    ];
    $fid = Company::logoId();
    $form['identity']['logo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Logo'),
      '#default_value' => $fid ? [$fid] : [],
      '#upload_location' => 'public://company',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'png gif jpg jpeg webp'],
      ],
      '#description' => $this->t('PNG or JPG. Used on printed orders.'),
    ];

    $form['vat'] = [
      '#type' => 'details',
      '#title' => $this->t('VAT'),
      '#open' => TRUE,
      '#weight' => -20,
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
      '#description' => $this->t('Thailand charges 7%. This is the rate, once, for the whole company; sales to Thailand use it, sales elsewhere are 0%, and which suppliers charge it is a separate answer on each contact card, under VAT treatment. Changing it here affects orders raised from now on: an order keeps the rate it was raised under, so nothing already printed and sent moves.'),
    ];

    $form['bank'] = [
      '#type' => 'details',
      '#title' => $this->t('Bank'),
      '#open' => TRUE,
      '#weight' => -10,
      '#description' => $this->t('Printed on the proforma so the customer knows where to pay. Leave blank until you have the account to show.'),
    ];
    $form['bank']['bank_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Bank name'),
      '#default_value' => Company::bankName(),
      '#maxlength' => 255,
    ];
    $form['bank']['bank_holder'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account holder'),
      '#default_value' => Company::bankHolder(),
      '#maxlength' => 255,
    ];
    $form['bank']['bank_account'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account number'),
      '#default_value' => Company::bankAccount(),
      '#maxlength' => 128,
    ];
    $form['bank']['bank_swift'] = [
      '#type' => 'textfield',
      '#title' => $this->t('SWIFT / BIC'),
      '#default_value' => Company::bankSwift(),
      '#maxlength' => 32,
    ];

    $form['tiles'] = [
      '#type' => 'details',
      '#title' => $this->t('Home page icons'),
      '#open' => FALSE,
      '#weight' => 10,
      '#description' => $this->t('Each icon on the home page is an ordinary page, so that it sits on the grid with the rest. Opening one sends you straight to the screen it stands for, and this is where that pairing is set. You should only need this after rebuilding the home page.'),
    ];

    $nodes = \Drupal::entityTypeManager()->getStorage('node');
    $routes = \Drupal::service('router.route_provider');

    foreach (QueueTileRedirectSubscriber::TILES as $key => $route_name) {
      // Named after the screen's own title, so the two cannot end up disagreeing
      // about what a screen is called.
      try {
        $route = $routes->getRouteByName($route_name);
        $path = $route->getPath();
        // Views pages often have a title callback and no _title default. The
        // path is a worse label than the screen's name, but it is better than
        // a blank "Icon that opens" row.
        $screen = $route->getDefault('_title') ?: $path;
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
    $settings = $this->config(Company::CONFIG);

    $settings->set('legal_name', trim((string) $form_state->getValue('legal_name')));
    $settings->set('tax_id', trim((string) $form_state->getValue('tax_id')));
    $settings->set('phone', trim((string) $form_state->getValue('phone')));
    $settings->set('email', trim((string) $form_state->getValue('email')));
    $settings->set('website', trim((string) $form_state->getValue('website')));
    $settings->set('address', $this->addressValue($form_state->getValue('address')));

    $fids = $form_state->getValue('logo') ?: [];
    $new_logo = (int) reset($fids);
    $this->rememberLogo((int) $settings->get('logo'), $new_logo);
    $settings->set('logo', $new_logo);

    $settings->set('bank_name', trim((string) $form_state->getValue('bank_name')));
    $settings->set('bank_holder', trim((string) $form_state->getValue('bank_holder')));
    $settings->set('bank_account', trim((string) $form_state->getValue('bank_account')));
    $settings->set('bank_swift', strtoupper(trim((string) $form_state->getValue('bank_swift'))));

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

  /**
   * Address as config can store it: every key present, no NULLs.
   */
  private function addressValue($value): array {
    $address = Address::applyDefaults(is_array($value) ? $value : []);
    foreach ($address as $key => $part) {
      $address[$key] = $part === NULL ? '' : (string) $part;
    }
    return $address;
  }

  /**
   * Keep the new logo, drop the previous one if nothing else uses it.
   */
  private function rememberLogo(int $old, int $new): void {
    $usage = \Drupal::service('file.usage');
    $files = \Drupal::entityTypeManager()->getStorage('file');

    if ($new > 0 && $new !== $old) {
      $file = $files->load($new);
      if ($file instanceof FileInterface) {
        $file->setPermanent();
        $file->save();
        $usage->add($file, 'tec_production', 'config', 'logo');
      }
    }

    if ($old > 0 && $old !== $new) {
      $file = $files->load($old);
      if ($file instanceof FileInterface) {
        $usage->delete($file, 'tec_production', 'config', 'logo');
        if (!$usage->listUsage($file)) {
          $file->delete();
        }
      }
    }
  }

}
