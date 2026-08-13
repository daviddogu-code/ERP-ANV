#!/bin/bash
# Subtheme Commands Tests

section "Subtheme Commands"

# Test the renamed command with new alias.
assert_runs "subtheme:create alias dxt-sc is registered" \
  "drush help dxt:subtheme:create"

# Test backwards-compat alias.
assert_runs "subtheme:create alias dxpr-cs is registered" \
  "drush help dxpr:create-subtheme"

# Actually creating a subtheme requires dxpr_theme starterkit on disk.
# Only test if the starterkit directory exists.
DRUPAL_ROOT=$(drush php:eval 'echo DRUPAL_ROOT;' 2>/dev/null)
STARTERKIT="$DRUPAL_ROOT/themes/contrib/dxpr_theme/dxpr_theme_STARTERKIT"

if [ -d "$STARTERKIT" ]; then
  assert_runs "subtheme:create creates a subtheme" \
    "drush dxt:subtheme:create e2e_test_subtheme --theme-name='E2E Test Subtheme'"

  assert_runs "subtheme directory exists" \
    "test -d '$DRUPAL_ROOT/themes/custom/e2e_test_subtheme'"

  assert_runs "subtheme info.yml exists" \
    "test -f '$DRUPAL_ROOT/themes/custom/e2e_test_subtheme/e2e_test_subtheme.info.yml'"

  assert_fail "subtheme:create duplicate fails" \
    "drush dxt:subtheme:create e2e_test_subtheme"

  # Cleanup.
  rm -rf "$DRUPAL_ROOT/themes/custom/e2e_test_subtheme"
else
  echo "  Skipping subtheme creation tests — starterkit not found"
fi
