<?php

namespace Drupal\tec_production;

use Drupal\Core\Template\Attribute;
use Drupal\views\ViewExecutable;

/**
 * The last line of a sales order's table: how many pieces, and how much money.
 *
 * A figure has to sit under the column it is about. Until 19 August 2026 this
 * foot was `attachment_1`, a second little view glued underneath the table, and
 * from there it could only ever float against the right edge: two tables never
 * agree on their column widths, because the browser sizes each one on its own
 * contents. No amount of CSS fixes that. The only place a number can line up
 * with a column is inside the same table, so that is where this puts it -- one
 * more row, with the count under Qty and the money under Item total.
 *
 * It is drawn on the server, and it has to be: this same table is the Pro Forma
 * that gets printed and handed to a customer. The draft screens do the same job
 * in the browser, and they are right to -- there the figures move as quantities
 * are typed, before anything is saved -- but a piece of paper cannot depend on
 * a script having run.
 *
 * The column is found **by name and never by counting**, which is the same rule
 * the draft's script follows: Views names every cell after the field that fills
 * it. Both feet ask the same question in the same words, so the day somebody
 * adds a column, both move.
 *
 * Nothing here needs cache tags of its own. The sums are taken from the rows
 * the view has already loaded, so whatever invalidates the table invalidates
 * the foot with it. The purchase foot next door does carry tags, because it
 * loads the order to read a VAT rate that is on no row in front of it.
 */
final class OrderFoot {

  /**
   * The tables that get a foot, and nothing else does.
   *
   * Named one by one instead of guessed at. The same view draws nine other
   * screens -- the two drafts, the purchase side, the blocks nobody reaches --
   * and the drafts in particular must not get a second foot on top of the one
   * their script writes.
   *
   * Purchases are absent on purpose: their foot is three lines and belongs to
   * VatTotals, and the owner asked for no piece count there.
   */
  public const SCREENS = [
    'tec_order_sales_order_line_items' => ['block_1', 'page_4'],
  ];

  /**
   * The column that is counted.
   */
  public const PIECES = 'field_tec_quantity';

  /**
   * The column that is added up, and the last one on both screens.
   */
  public const MONEY = 'field_tec_line_item_total_number';

  /**
   * Puts the foot on the tables that have one.
   */
  public static function add(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if (!$view instanceof ViewExecutable
      || !in_array($view->current_display, self::SCREENS[$view->id()] ?? [], TRUE)) {
      return;
    }

    // An order with no lines has no table worth adding to, and a table of one
    // column has nowhere to put two figures.
    $columns = array_keys($variables['header'] ?? []);
    if (count($columns) < 2 || empty($variables['rows'])) {
      return;
    }

    [$pieces, $money] = self::sums($view);

    $last = count($columns) - 1;
    $at = array_search(self::PIECES, $columns, TRUE);

    // The run of columns the label used to span is cut in two by the column
    // being counted, so the label takes what is left between that and the
    // money. With nothing left -- a quantity column so far right that the
    // label would have nowhere to go -- the row goes back to the two cells it
    // would have been, because a label with no room is worse than a count that
    // is not shown.
    $room = $at === FALSE ? 0 : $last - $at - 1;

    $cells = [];
    if ($at !== FALSE && $room > 0) {
      if ($at > 0) {
        $cells[] = self::cell([], $at, NULL);
      }
      $cells[] = self::cell([self::alignmentOf($variables, $columns[$at])], 1,
        self::figure(number_format($pieces), 'grand-total-pieces'));
      $cells[] = self::cell(['views-align-right'], $room, self::figure(self::label(), ''));
    }
    else {
      $cells[] = self::cell(['views-align-right'], max(1, $last), self::figure(self::label(), ''));
    }
    $cells[] = self::cell(['views-align-right'], 1,
      self::figure(self::money($money), 'grand-total-value'));

    // The same two class names the draft's script uses for its own foot, so
    // that anything ever written to style one styles both.
    $variables['rows'][] = [
      'attributes' => new Attribute(['class' => ['tec-foot-row', 'grand-total-row']]),
      'columns' => $cells,
    ];
  }

  /**
   * What the rows on the screen add up to.
   *
   * Read off the rows the view has in hand rather than asked of the database
   * again, so the foot can only ever say what the table above it says. These
   * two screens show every line of one order, so there is no pager to fall foul
   * of; if one ever grew a pager, the foot would start counting a page and the
   * fix would be to sum the query, not to sum harder here.
   *
   * @return array
   *   The piece count and the money, in that order.
   */
  private static function sums(ViewExecutable $view): array {
    $pieces = 0;
    $money = 0.0;

    foreach ($view->result as $row) {
      $line = $row->_entity ?? NULL;
      if (!$line) {
        continue;
      }
      if ($line->hasField(self::PIECES)) {
        $pieces += (int) $line->get(self::PIECES)->value;
      }
      if ($line->hasField(self::MONEY)) {
        $money += (float) $line->get(self::MONEY)->value;
      }
    }

    return [$pieces, $money];
  }

  /**
   * The alignment of a column, so the figure leans the way its column leans.
   *
   * Views writes the alignment into the class of every cell of the column, and
   * that list is handed to the template as `fields`. Copying it from there is
   * how the count ends up over the numbers it counted rather than beside them.
   */
  private static function alignmentOf(array $variables, string $column): string {
    $classes = (string) ($variables['fields'][$column] ?? '');

    return preg_match('/(?:views|text)-align-\w+/', $classes, $found) ? $found[0] : '';
  }

  /**
   * One cell of the foot.
   */
  private static function cell(array $classes, int $span, ?array $content): array {
    $attributes = new Attribute();
    if ($classes = array_filter($classes)) {
      $attributes->addClass($classes);
    }
    if ($span > 1) {
      $attributes->setAttribute('colspan', $span);
    }

    return [
      // The template only builds a class list for cells that ask for the
      // default classes, and it builds it inside the loop. A cell that does not
      // ask is handed whatever the last cell that did left behind -- a Twig
      // scoping trap, not an intention -- so this asks, with no fields to name.
      'default_classes' => TRUE,
      'fields' => [],
      'attributes' => $attributes,
      'content' => $content === NULL ? [] : [['field_output' => $content]],
    ];
  }

  /**
   * A figure of the foot, told apart from the lines above it.
   */
  private static function figure($text, string $class): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'strong',
      '#attributes' => $class === '' ? [] : ['class' => [$class]],
      '#value' => $text,
    ];
  }

  /**
   * What the money is called. The wording the screen has always used.
   */
  private static function label() {
    return \Drupal::translation()->translate('Total');
  }

  /**
   * Money the way every other figure on these screens is written.
   *
   * The symbol is escaped rather than typed for the same reason the purchase
   * foot escapes it: this file has no business depending on surviving a trip
   * through an editor that guesses at encodings, and several in this project
   * have not.
   */
  private static function money(float $amount): string {
    return "\u{0E3F} " . number_format($amount, 2);
  }

}
