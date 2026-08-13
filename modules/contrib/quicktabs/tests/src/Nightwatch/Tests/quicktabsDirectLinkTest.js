/**
 * @file
 * Tests Quick Tabs direct linking via URL fragments.
 */

const path = '/quicktabs-directlink-test';
const multiplePath = '/quicktabs-directlink-test/multiple';
const wrapper = '#quicktabs-qtdl_test';
const firstPage = '#quicktabs-tabpage-qtdl_test-0';
const secondPage = '#quicktabs-tabpage-qtdl_test-1';
const firstTab = 'a[data-quicktabs-tab-name="first-tab"]';
const secondTab = 'a[data-quicktabs-tab-name="second-tab"]';

// Selectors for the two instances rendered on the "multiple" page. Both use the
// same tab slugs, so the second-tab link must be scoped to its own instance.
const aSecondTab = `#quicktabs-qtdl_a ${secondTab}`;
const aFirstPage = '#quicktabs-tabpage-qtdl_a-0';
const aSecondPage = '#quicktabs-tabpage-qtdl_a-1';
const bSecondTab = `#quicktabs-qtdl_b ${secondTab}`;
const bFirstPage = '#quicktabs-tabpage-qtdl_b-0';
const bSecondPage = '#quicktabs-tabpage-qtdl_b-1';

module.exports = {
  '@tags': ['quicktabs'],
  before(browser) {
    browser
      .drupalInstall()
      .drupalInstallModule('quicktabs_directlink_test', true);
  },
  after(browser) {
    browser.drupalUninstall();
  },

  'Loading a direct link activates the matching tab': (browser) => {
    // Tab memory is disabled for these instances, so activating the second
    // tab can only be the result of the URL fragment.
    browser
      .drupalRelativeURL(`${path}#qt-qtdl_test--second-tab`)
      .waitForElementVisible(wrapper)
      .waitForElementVisible(secondPage)
      .assert.not.visible(firstPage);
  },

  'Clicking a tab updates the URL fragment': (browser) => {
    // Force a fresh page load so the first tab is active before the click.
    browser
      .drupalRelativeURL('/')
      .drupalRelativeURL(path)
      .waitForElementVisible(wrapper)
      .waitForElementVisible(firstPage)
      .assert.not.visible(secondPage)
      .click(secondTab)
      .waitForElementVisible(secondPage)
      .assert.not.visible(firstPage)
      .assert.urlContains('#qt-qtdl_test--second-tab');
  },

  'Direct links record the last clicked instance': (browser) => {
    browser
      .drupalRelativeURL(multiplePath)
      .waitForElementVisible('#quicktabs-qtdl_a')
      .waitForElementVisible('#quicktabs-qtdl_b')
      .assert.visible(aFirstPage)
      .assert.not.visible(aSecondPage)
      .assert.visible(bFirstPage)
      .assert.not.visible(bSecondPage)
      // Activating a tab in instance A must not affect instance B.
      .click(aSecondTab)
      .waitForElementVisible(aSecondPage)
      .assert.visible(bFirstPage)
      .assert.not.visible(bSecondPage)
      .assert.urlContains('#qt-qtdl_a--second-tab')
      // Activating a tab in instance B leaves A untouched, but replaces the
      // fragment because direct links record one active instance.
      .click(bSecondTab)
      .waitForElementVisible(bSecondPage)
      .assert.visible(aSecondPage)
      .execute(
        function getHash() {
          return window.location.hash;
        },
        [],
        function assertHash(result) {
          this.assert.equal(result.value, '#qt-qtdl_b--second-tab');
        },
      );
  },

  'Back button restores the previously active tab': (browser) => {
    browser
      .drupalRelativeURL('/')
      .drupalRelativeURL(path)
      .waitForElementVisible(wrapper)
      .waitForElementVisible(firstPage)
      .click(secondTab)
      .waitForElementVisible(secondPage)
      .click(firstTab)
      .waitForElementVisible(firstPage)
      .assert.not.visible(secondPage)
      // Going back re-activates the previously selected tab via hashchange.
      .back()
      .waitForElementVisible(secondPage)
      .assert.not.visible(firstPage)
      .assert.urlContains('#qt-qtdl_test--second-tab');
  },
};
