<?php

namespace Drupal\Tests\quicktabs\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\quicktabs\Entity\QuickTabsInstance;

/**
 * Tests the "remember last clicked tab" option.
 *
 * @group quicktabs
 */
class RememberLastClickedTabTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'quicktabs',
  ];

  /**
   * When enabled, the last clicked tab is restored on the next page load.
   */
  public function testRememberEnabledRestoresLastClickedTab(): void {
    $this->placeInstanceBlock('remember_on', TRUE);

    $assert = $this->assertSession();
    $this->drupalGet('<front>');

    // The default (first) tab is active on a fresh load.
    $assert->waitForElement('css', '#quicktabs-remember_on');
    $this->assertTrue($this->tabpage('remember_on', 0)->isVisible());
    $this->assertFalse($this->tabpage('remember_on', 1)->isVisible());

    // Click the second tab; it becomes active and is saved to localStorage.
    $this->clickTab('remember_on', 'second-tab');
    $assert->waitForElementVisible('css', '#quicktabs-tabpage-remember_on-1');
    $this->assertFalse($this->tabpage('remember_on', 0)->isVisible());

    // Reload: the second tab is restored from localStorage.
    $this->drupalGet('<front>');
    $assert->waitForElementVisible('css', '#quicktabs-tabpage-remember_on-1');
    $this->assertTrue($this->tabpage('remember_on', 1)->isVisible());
    $this->assertFalse($this->tabpage('remember_on', 0)->isVisible());
  }

  /**
   * When disabled, the default tab is shown again on the next page load.
   */
  public function testRememberDisabledDoesNotRestoreTab(): void {
    $this->placeInstanceBlock('remember_off', FALSE);

    $assert = $this->assertSession();
    $this->drupalGet('<front>');

    $assert->waitForElement('css', '#quicktabs-remember_off');
    $this->assertTrue($this->tabpage('remember_off', 0)->isVisible());

    // Clicking still switches tabs in the current page view.
    $this->clickTab('remember_off', 'second-tab');
    $assert->waitForElementVisible('css', '#quicktabs-tabpage-remember_off-1');

    // Reload: the default (first) tab is active again, nothing was remembered.
    $this->drupalGet('<front>');
    $assert->waitForElement('css', '#quicktabs-remember_off');
    $this->assertTrue($this->tabpage('remember_off', 0)->isVisible());
    $this->assertFalse($this->tabpage('remember_off', 1)->isVisible());
  }

  /**
   * Creates a two-tab instance and places its block in the content region.
   *
   * @param string $id
   *   The instance ID.
   * @param bool $remember
   *   Whether the remember last clicked tab option is enabled.
   */
  protected function placeInstanceBlock(string $id, bool $remember): void {
    QuickTabsInstance::create([
      'id' => $id,
      'label' => $id,
      'renderer' => 'quick_tabs',
      'options' => [
        'quick_tabs' => [
          'ajax' => FALSE,
          'style' => '',
          'class' => '',
          'direct_linking' => FALSE,
        ],
      ],
      'hide_empty_tabs' => FALSE,
      'remember_last_clicked_tab' => $remember,
      'default_tab' => 0,
      'configuration_data' => [
        [
          'title' => 'First Tab',
          'type' => 'block_content',
          'content' => [
            'block_content' => ['options' => ['bid' => 'system_powered_by_block']],
          ],
        ],
        [
          'title' => 'Second Tab',
          'type' => 'block_content',
          'content' => [
            'block_content' => ['options' => ['bid' => 'system_powered_by_block']],
          ],
        ],
      ],
    ])->save();

    $this->drupalPlaceBlock('quicktabs_block:' . $id, ['region' => 'content']);
  }

  /**
   * Returns a tab page element for the given instance and tab index.
   */
  protected function tabpage(string $id, int $index) {
    return $this->getSession()->getPage()->find('css', "#quicktabs-tabpage-$id-$index");
  }

  /**
   * Clicks a tab link by its instance ID and tab slug.
   */
  protected function clickTab(string $id, string $slug): void {
    $this->getSession()->getPage()
      ->find('css', "#quicktabs-$id a[data-quicktabs-tab-name=\"$slug\"]")
      ->click();
  }

}
