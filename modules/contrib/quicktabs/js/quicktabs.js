/**
 * @file
 * QuickTabs JavaScript functionality.
 *
 * Provides tab switching functionality including:
 * - Click handlers for tab navigation
 * - Keyboard navigation support
 * - Direct linking via URL fragments
 * - Browser back/forward support
 * - localStorage-based tab memory
 */

(function ($, Drupal, drupalSettings, once) {
  Drupal.quicktabs = Drupal.quicktabs || {};

  Drupal.quicktabs.getQTName = function (el) {
    return el.attr('id').substring(el.attr('id').indexOf('-') + 1);
  };

  // Returns the direct-link slug for a tab element (server-provided attribute).
  Drupal.quicktabs.getTabSlug = function (element) {
    const slug = $(element).attr('data-quicktabs-tab-name');
    if (typeof slug !== 'undefined' && slug !== null && slug !== '') {
      return slug;
    }
    // Fallback: derive from the instance name and tab index.
    const tabIndex =
      typeof element.myTabIndex !== 'undefined'
        ? element.myTabIndex
        : $(element).data('myTabIndex');
    return `${element.qtName}-${tabIndex}`;
  };

  // Parses the selector-safe fragment ("#qt-<name>--<slug>") into a
  // {qtName: slug} map.
  Drupal.quicktabs.parseFragment = function () {
    const map = {};
    const hash = window.location.hash.replace(/^#/, '');
    if (!hash) {
      return map;
    }

    // Avoid "=" because Drupal core form.js treats hashes as selectors.
    const canonicalMatch = hash.match(/^qt-(.+?)--(.+)$/);
    if (canonicalMatch) {
      map[canonicalMatch[1]] = canonicalMatch[2];
    }
    return map;
  };

  // Serializes one active Quicktabs instance back into a selector-safe fragment
  // (without leading "#").
  Drupal.quicktabs.serializeFragment = function (qtName, slug) {
    return `qt-${qtName}--${slug}`;
  };

  Drupal.behaviors.quicktabs = {
    attach(context, settings) {
      $(once('quicktabs-wrapper', 'div.quicktabs-wrapper', context)).each(
        function () {
          const el = $(this);
          Drupal.quicktabs.prepare(el);
          Drupal.quicktabs.processDirectLink(el);
        },
      );
    },
  };

  // Setting up the initial behaviors
  Drupal.quicktabs.prepare = function (el) {
    // el.id format: "quicktabs-$name"
    const qtName = Drupal.quicktabs.getQTName(el);
    const $ul = $(el).find('ul.quicktabs-tabs:first');
    $ul.find('li a').each(function (i, element) {
      element.myTabIndex = i;
      element.qtName = qtName;
      const tab = new Drupal.quicktabs.tab(element);
      $(element).parents('li').get(0);
      $(element).on('click', { tab }, Drupal.quicktabs.clickHandler);
      $(element).on(
        'keydown',
        { myTabIndex: i },
        Drupal.quicktabs.keyDownHandler,
      );
    });
  };

  // Process direct links from URL fragments.
  Drupal.quicktabs.processDirectLink = function (el) {
    const qtName = Drupal.quicktabs.getQTName(el);

    // Check if direct linking is enabled for this instance.
    const qtKey = `qt_${qtName}`;
    if (
      !drupalSettings.quicktabs ||
      !drupalSettings.quicktabs[qtKey] ||
      drupalSettings.quicktabs[qtKey].directLinking !== true
    ) {
      return; // Direct linking is disabled, skip processing.
    }

    // Activate the tab named in this instance's fragment segment, if any.
    const activate = function () {
      const slug = Drupal.quicktabs.parseFragment()[qtName];
      if (!slug) {
        return;
      }
      const targetTab = Drupal.quicktabs.findTabBySlug(el, slug);
      if (targetTab) {
        targetTab.click();
      }
    };

    // Defer the initial activation so memory restore and DOM setup run first.
    setTimeout(activate, 0);

    // Re-activate on back/forward navigation. Namespaced and de-duped so the
    // handler is not bound twice if the wrapper is re-attached.
    $(window)
      .off(`hashchange.quicktabs-${qtName}`)
      .on(`hashchange.quicktabs-${qtName}`, activate);
  };

  // Find a tab in an instance by its direct-link slug.
  Drupal.quicktabs.findTabBySlug = function (el, slug) {
    let targetTab = null;
    $(el)
      .find('ul.quicktabs-tabs:first li a')
      .each(function (i, element) {
        if (Drupal.quicktabs.getTabSlug(element) === slug) {
          targetTab = element;
          return false; // Break the loop.
        }
      });
    return targetTab;
  };

  Drupal.quicktabs.clickHandler = function (event) {
    let tab = event.data.tab;
    const element = this;

    // Set clicked tab to active.
    $(this).parents('li').siblings().removeClass('active');
    $(this).parents('li').siblings().attr('aria-selected', 'false');
    $(this).parents('li').siblings().find('a').attr('tabindex', '-1');
    $(this).parents('li').addClass('active');
    $(this).parents('li').attr('aria-selected', 'true');
    $(this).attr('tabindex', '0');

    if ($(this).hasClass('use-ajax')) {
      $(this).addClass('quicktabs-loaded');
    }

    // Hide all tabpages.
    tab.container.children().addClass('quicktabs-hide');

    if (!tab.tabpage.hasClass('quicktabs-tabpage')) {
      tab = new Drupal.quicktabs.tab(element);
    }

    tab.tabpage.removeClass('quicktabs-hide');

    // Update the URL fragment only for genuine user interactions. Programmatic
    // clicks (memory restore, direct-link activation) have no originalEvent, so
    // restoring a tab on page load does not rewrite the URL.
    if (event && event.originalEvent) {
      Drupal.quicktabs.updateUrlFragment(element);
    }

    return false;
  };

  // Update the URL fragment when a tab is activated. The URL records only the
  // last clicked Quicktabs instance so the hash remains selector-safe.
  Drupal.quicktabs.updateUrlFragment = function (element) {
    const qtName = element.qtName;
    const qtKey = `qt_${qtName}`;
    if (
      !drupalSettings.quicktabs ||
      !drupalSettings.quicktabs[qtKey] ||
      drupalSettings.quicktabs[qtKey].directLinking !== true
    ) {
      return;
    }
    window.location.hash = Drupal.quicktabs.serializeFragment(
      qtName,
      Drupal.quicktabs.getTabSlug(element),
    );
  };

  Drupal.quicktabs.keyDownHandler = function (event) {
    const tabIndex = event.data.myTabIndex;

    const tabs = $(this).parent('li').parent('ul').find('li a');

    switch (event.key) {
      case 'ArrowLeft':
      case 'ArrowUp':
        event.preventDefault();
        if (tabIndex <= 0) {
          tabs[tabs.length - 1].click();
          tabs[tabs.length - 1].focus();
        } else {
          tabs[tabIndex - 1].click();
          tabs[tabIndex - 1].focus();
        }
        break;
      case 'ArrowRight':
      case 'ArrowDown':
        event.preventDefault();
        if (tabIndex >= tabs.length - 1) {
          tabs[0].click();
          tabs[0].focus();
        } else {
          tabs[tabIndex + 1].click();
          tabs[tabIndex + 1].focus();
        }
    }
  };

  // Constructor for an individual tab
  Drupal.quicktabs.tab = function (el) {
    this.element = el;
    this.tabIndex = el.myTabIndex;
    const qtKey = `qt_${el.qtName}`;

    let i;
    for (i = 0; i < drupalSettings.quicktabs[qtKey].tabs.length; i++) {
      if (i === this.tabIndex) {
        this.tabObj = drupalSettings.quicktabs[qtKey].tabs[i];
        this.tabKey =
          typeof el.dataset.quicktabsTabIndex !== 'undefined'
            ? el.dataset.quicktabsTabIndex
            : i;
      }
    }

    this.tabpageId = `quicktabs-tabpage-${el.qtName}-${this.tabKey}`;
    this.container = $(`#quicktabs-container-${el.qtName}`);
    this.tabpage = this.container.find(`#${this.tabpageId}`);
  };

  // Enable tab memory (using localStorage)
  Drupal.behaviors.quicktabsmemory = {
    attach(context, settings) {
      $(once('form-group', 'div.quicktabs-wrapper', context)).each(function () {
        const el = $(this);

        // Only remember last clicked tab if the option is enabled.
        if (el.data('rememberLast')) {
          const qtName = Drupal.quicktabs.getQTName(el);
          const $ul = $(el).find('ul.quicktabs-tabs:first');
          const storageKey = `Drupal.quicktabs.activeTab.${qtName}`;

          $ul.find('li a').each(function (i, element) {
            const $link = $(element);
            $link.data('myTabIndex', i);

            // Click the tab if a saved index exists in localStorage.
            const savedIndex = localStorage.getItem(storageKey);
            if (
              savedIndex !== null &&
              parseInt(savedIndex, 10) === $link.data('myTabIndex')
            ) {
              $(element).trigger('click');
            }

            // Set the click handler for all tabs, this updates localStorage on every tab click.
            $link.on('click', function () {
              const $linkdata = $(this);
              const tabIndex = $linkdata.data('myTabIndex');
              localStorage.setItem(storageKey, tabIndex);
            });
          });
        }
      });
    },
  };

  if (Drupal.Ajax) {
    Drupal.Ajax.prototype.eventResponse = function (element, event) {
      event.preventDefault();
      event.stopPropagation();

      const ajax = this;

      if (ajax.ajaxing) {
        return;
      }

      try {
        if (ajax.$form) {
          if (ajax.setClick) {
            element.form.clk = element;
          }

          ajax.$form.ajaxSubmit(ajax.options);
        } else if (!$(element).hasClass('quicktabs-loaded')) {
          ajax.beforeSerialize(ajax.element, ajax.options);
          $.ajax(ajax.options);
        }
      } catch (e) {
        ajax.ajaxing = false;
        window.alert(
          `An error occurred while attempting to process ${ajax.options.url}: ${e.message}`,
        );
      }
    };
  }
})(jQuery, Drupal, drupalSettings, once);
