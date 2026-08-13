<?php

namespace Drupal\Tests\eca_tamper\Kernel;

use Drupal\Core\Action\ActionManager;
use Drupal\eca\Token\TokenInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\tamper\Plugin\Tamper\Trim;

/**
 * Tests special character replacement in eca_tamper TamperTrait.
 */
class TamperTraitSpecialCharacterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'eca',
    'eca_tamper',
    'modeler_api',
    'tamper',
  ];

  /**
   * The action manager.
   */
  protected ActionManager $actionManager;

  /**
   * The token service.
   */
  protected TokenInterface $tokenService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->actionManager = \Drupal::service('plugin.manager.action');
    $this->tokenService = \Drupal::service('eca.token_services');
  }

  /**
   * Tests that escape sequences are properly converted for Trim plugin.
   */
  public function testSpecialCharacterReplacement(): void {
    $expected_result = 'Hello World';
    $config = [
      'eca_data' => "\0\t\n\r$expected_result\r\n\t\0",
      'eca_token_name' => 'result',
      Trim::SETTING_CHARACTER => '\t\n\r\0',
      Trim::SETTING_SIDE => 'trim',
    ];

    /** @var \Drupal\eca_tamper\Plugin\Action\Tamper $action */
    $action = $this->actionManager->createInstance('eca_tamper:trim', $config);
    $this->assertTrue($action->access(NULL));
    $action->execute();

    $result = $this->tokenService->getTokenData($config['eca_token_name'])->getValue();
    $this->assertEquals($expected_result, $result,
      "Failed to properly trim '{$config['eca_data']}' with character mask '{$config[Trim::SETTING_CHARACTER]}'");
  }

}
