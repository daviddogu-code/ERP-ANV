#!/bin/bash
# Page Commands Tests

section "Page Commands"

# Get the test node NID (created in run-e2e-tests.sh).
TEST_NID=$(drush php:eval '$ids = \Drupal::entityTypeManager()->getStorage("node")->getQuery()->accessCheck(FALSE)->condition("title", "E2E Test Page")->execute(); echo reset($ids);' 2>/dev/null)

if [ -z "$TEST_NID" ] || [ "$TEST_NID" = "" ]; then
  echo "  Skipping page tests — no test node found"
else
  # page:get
  assert_success "page:get returns success" \
    "drush dxt:page:get $TEST_NID"

  assert_has "page:get returns fields" \
    "drush dxt:page:get $TEST_NID" "fields"

  assert_eq "page:get returns correct title" \
    "drush dxt:page:get $TEST_NID" '.title' "E2E Test Page"

  assert_fail "page:get nonexistent node fails" \
    "drush dxt:page:get 999999"

  # page:set (only if DTH fields exist).
  HAS_LAYOUT=$(drush dxt:page:get "$TEST_NID" 2>/dev/null | yq -r '.fields.field_dth_page_layout // "missing"' 2>/dev/null)
  if [ "$HAS_LAYOUT" != "missing" ]; then
    assert_dry_run "page:set dry-run succeeds" \
      "drush dxt:page:set $TEST_NID --layout=fullwidth --dry-run"

    assert_success "page:set layout succeeds" \
      "drush dxt:page:set $TEST_NID --layout=fullwidth"

    assert_fail "page:set invalid layout fails" \
      "drush dxt:page:set $TEST_NID --layout=invalid"
  else
    echo "  Skipping page:set tests — DTH fields not installed on page type"
  fi
fi

# Error cases.
assert_fail "page:set no options fails" \
  "drush dxt:page:set 1"

assert_fail "page:get nonexistent fails" \
  "drush dxt:page:get 999999"
