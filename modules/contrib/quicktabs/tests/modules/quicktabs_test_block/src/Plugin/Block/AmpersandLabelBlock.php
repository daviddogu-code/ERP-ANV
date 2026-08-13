<?php

namespace Drupal\quicktabs_test_block\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A test block whose admin label contains a special character.
 *
 * The "&" is passed through a placeholder so the rendered admin label is
 * already HTML-encoded ("Meetings &amp; Events"), mirroring how Views derives
 * block admin labels. Casting such a Markup object to a string yields encoded
 * HTML, which the Quick Tabs block selector must not encode a second time.
 *
 * @see \Drupal\views\Plugin\Derivative\ViewsBlock
 * @see \Drupal\quicktabs\Plugin\TabType\BlockContent::getBlockOptions()
 */
#[Block(
  id: "quicktabs_test_ampersand_block",
  admin_label: new TranslatableMarkup("@name", ["@name" => "Meetings & Events"]),
)]
class AmpersandLabelBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return ['#markup' => 'Meetings & Events block body'];
  }

}
