#!/bin/bash
set -e

# Parse arguments.
TEST_FILTER="$1"

# Install required PHP extensions.
apk add --no-cache libpng libpng-dev libjpeg-turbo-dev libwebp-dev zlib-dev libxpm-dev > /dev/null 2>&1
docker-php-ext-install gd > /dev/null 2>&1

echo "=== Installing Drupal with SQLite ==="
composer create-project drupal/recommended-project:11.x-dev /tmp/drupal --no-interaction --quiet
cd /tmp/drupal

mkdir -p web/modules/contrib
ln -s /src web/modules/contrib/dxpr_theme_helper

# Also install dxpr_theme for full integration testing.
mkdir -p web/themes/contrib
if [ -d /src/../dxpr_theme ]; then
  ln -s /src/../dxpr_theme web/themes/contrib/dxpr_theme
fi

composer require drush/drush drupal/media_library_form_element drupal/bootstrap5 --quiet

./vendor/bin/drush site:install standard \
  --db-url=sqlite://sites/default/files/.sqlite \
  --site-name="DXPR Theme Test" \
  --site-mail="test@example.com" \
  --yes \
  --quiet

# Set module version so dxpr_theme's >=3.1.0 constraint is satisfied.
# In dev/CI there is no packaged version, so inject one.
if ! grep -q '^version:' web/modules/contrib/dxpr_theme_helper/dxpr_theme_helper.info.yml; then
  echo "version: 3.9.9" >> web/modules/contrib/dxpr_theme_helper/dxpr_theme_helper.info.yml
fi

# Enable the module first (dxpr_theme depends on it).
./vendor/bin/drush en dxpr_theme_helper --yes --quiet

# Install dxpr_theme and set as default.
# Must happen AFTER enabling dxpr_theme_helper because
# dxpr_theme depends on dxpr_theme_helper (>=3.1.0).
if [ -d web/themes/contrib/dxpr_theme ]; then
  ./vendor/bin/drush theme:install dxpr_theme --yes --quiet
  ./vendor/bin/drush config:set system.theme default dxpr_theme --yes --quiet
fi

# Rebuild cache.
./vendor/bin/drush cr --quiet

echo "=== Setting up test data ==="

# Add DTH fields to the page content type for page:get/set tests.
./vendor/bin/drush php:eval '
  use Drupal\field\Entity\FieldStorageConfig;
  use Drupal\field\Entity\FieldConfig;

  $fields = [
    "field_dth_page_layout" => ["type" => "list_string", "settings" => ["allowed_values" => ["fullwidth" => "Full Width", "boxed" => "Boxed"]]],
    "field_dth_hide_regions" => ["type" => "string", "cardinality" => -1],
    "field_dth_main_content_width" => ["type" => "list_string", "settings" => ["allowed_values" => [
      "dxpr-theme-util-full-width-content" => "Full",
      "dxpr-theme-util-content-center-4-col" => "1/3",
      "dxpr-theme-util-content-center-6-col" => "1/2",
      "dxpr-theme-util-content-center-8-col" => "2/3",
      "dxpr-theme-util-content-center-10-col" => "5/6",
    ]]],
  ];

  foreach ($fields as $name => $def) {
    $type = $def["type"];
    $cardinality = $def["cardinality"] ?? 1;
    $settings = $def["settings"] ?? [];

    if (!FieldStorageConfig::loadByName("node", $name)) {
      FieldStorageConfig::create([
        "field_name" => $name,
        "entity_type" => "node",
        "type" => $type,
        "cardinality" => $cardinality,
        "settings" => $settings,
      ])->save();
    }
    if (!FieldConfig::loadByName("node", "page", $name)) {
      FieldConfig::create([
        "field_name" => $name,
        "entity_type" => "node",
        "bundle" => "page",
        "label" => $name,
      ])->save();
    }
  }
  echo "DTH fields added to page content type\n";
'

# Rebuild cache so subsequent drush commands see the new fields.
./vendor/bin/drush cr --quiet

# Create a test page node.
./vendor/bin/drush php:eval '
  $node = \Drupal::entityTypeManager()->getStorage("node")->create([
    "type" => "page",
    "title" => "E2E Test Page",
    "status" => 1,
  ]);
  $node->save();
  echo "Test node created: " . $node->id() . "\n";
'

# Set some initial theme settings.
./vendor/bin/drush php:eval '
  $config = \Drupal::configFactory()->getEditable("dxpr_theme.settings");
  $config->set("header_top_layout", "0");
  $config->set("body_font_size", 14);
  $config->set("boxed_layout", 1);
  $config->save();
  echo "Initial theme settings configured\n";
'

./vendor/bin/drush cr --quiet

echo ""
echo "=========================================="
echo "       Running E2E Tests"
echo "=========================================="

# Disable exit on error for tests.
set +e

# Source helper functions.
source /src/scripts/e2e/_helpers.sh

# Run test files (filtered if argument provided).
if [ -n "$TEST_FILTER" ]; then
  for test_file in /src/scripts/e2e/test-*${TEST_FILTER}*.sh; do
    if [ -f "$test_file" ]; then
      source "$test_file"
    else
      echo "No test files matching: $TEST_FILTER"
      exit 1
    fi
  done
else
  for test_file in /src/scripts/e2e/test-*.sh; do
    source "$test_file"
  done
fi

# Print summary.
echo ""
echo "=========================================="
echo "               Results"
echo "=========================================="
echo -e "Passed: ${GREEN}$PASS${NC}"
echo -e "Failed: ${RED}$FAIL${NC}"
echo "=========================================="

if [ $FAIL -gt 0 ]; then
  exit 1
fi

echo ""
echo "All tests passed!"
