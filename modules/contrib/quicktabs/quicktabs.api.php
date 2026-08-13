<?php

/**
 * @file
 * Hooks provided by the Quick Tabs module.
 */

use Drupal\quicktabs\Entity\QuickTabsInstance;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alters a QuickTabs instance before it is rendered.
 *
 * Use this hook to change a QuickTabs instance at render time, for example to
 * inject a dynamic View argument based on the current user or page, reorder
 * tabs, or hide them.
 *
 * The hook fires once per request, before rendering, on both render paths:
 * - the full tabset, built by QuickTabsInstance::getRenderArray() (used by the
 *   block plugin);
 * - an individual tab loaded over AJAX, built by QuickTabsInstance::renderTab()
 *   (used by \Drupal\quicktabs\Controller\QuickTabsController).
 *
 * Because both paths run the same alter, a change made here applies whether a
 * tab is rendered with the page or fetched later by clicking it. A per-request
 * guard ensures the hook runs only once for a given instance, so an alter may
 * safely append to configuration rather than only replace it.
 *
 * Reordering caveat: AJAX tab links carry the tab's numeric index, and that
 * index is resolved against the configuration data *after* this hook runs. If
 * you reorder tabs, reindex them consistently (keys 0..N) so the index in an
 * AJAX request still resolves to the intended tab.
 *
 * Cacheability: if your alter makes the rendered output vary (for instance, by
 * deriving a View argument from the current user or the URL), declare it by
 * adding cache metadata to $qt. The instance is added as a cacheable dependency
 * on both render paths, so metadata added here propagates to the block render
 * and to the AJAX response:
 * @code
 * $qt->addCacheContexts(['user.roles']);
 * $qt->addCacheTags(['my_module:something']);
 * $qt->mergeCacheMaxAge(3600);
 * @endcode
 *
 * @param \Drupal\quicktabs\Entity\QuickTabsInstance $qt
 *   The QuickTabs instance being rendered. Modify it in place; reassigning the
 *   variable has no effect on the caller.
 */
function hook_quicktabs_instance_alter(QuickTabsInstance $qt) {
  // Filter the second tab's View to the current user, and declare that the
  // output now varies per user.
  if ($qt->id() === 'my_instance') {
    $uid = \Drupal::currentUser()->id();
    $tabs = $qt->getConfigurationData();
    $tabs[1]['content']['view_content']['options']['args'] = $uid;
    $qt->setConfigurationData($tabs);
    $qt->addCacheContexts(['user']);
  }
}

/**
 * @} End of "addtogroup hooks".
 */
