<?php

namespace Drupal\quicktabs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Cache\CacheableAjaxResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\quicktabs\Entity\QuickTabsInstanceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a controller for content retrieved through AJAX.
 */
class QuickTabsController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a QuickTabsController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, protected RendererInterface $renderer) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxContent($js, $instance, $tab) {
    if ($js === 'nojs') {
      return [];
    }
    else {
      $qt = $this->entityTypeManager->getStorage('quicktabs_instance')->load($instance);
      if (!$qt instanceof QuickTabsInstanceInterface) {
        throw new NotFoundHttpException();
      }
      $render = $qt->renderTab($tab);

      $ajax_response = new CacheableAjaxResponse();
      $ajax_response->addCacheableDependency($qt);

      if (is_array($render) && $render) {
        // Render here rather than leaving it to the AJAX command. A child
        // element or a #pre_render callback only contributes its cacheability
        // once rendered, and the command renders its own copy of the array, so
        // anything bubbled there would never reach the response — which is
        // what Dynamic Page Cache stores the response under.
        // @see \Drupal\Core\Ajax\CommandWithAttachedAssetsTrait::getRenderedContent()
        $markup = $this->renderer->renderRoot($render);
        $ajax_response->addCacheableDependency(CacheableMetadata::createFromRenderArray($render));
        // Rebuild rather than reusing $render: it is now marked #printed, and
        // would render as empty. Attachments bubbled during the render above
        // have to be carried over so the tab's assets still load.
        $render = [
          '#markup' => $markup,
          '#attached' => $render['#attached'] ?? [],
        ];
      }

      $selector = '#quicktabs-tabpage-' . $instance . '-' . $tab . ' .quicktabs-tabpage-content';
      $ajax_response->addCommand(new HtmlCommand($selector, $render));
      return $ajax_response;
    }
  }

}
