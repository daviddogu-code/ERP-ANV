#!/bin/bash
# Config Commands Tests

section "Config Commands — List"

assert_success "config:list returns success" \
  "drush dxt:config:list"

assert_not_empty "config:list returns items" \
  "drush dxt:config:list"

assert_success "config:list --sections-only returns success" \
  "drush dxt:config:list --sections-only"

assert_has "config:list --sections-only has sections" \
  "drush dxt:config:list --sections-only" "sections"

assert_success "config:list --section=header returns success" \
  "drush dxt:config:list --section=header"

assert_success "config:list --section=header --detail returns success" \
  "drush dxt:config:list --section=header --detail"

assert_runs "config:list --keys-only runs" \
  "drush dxt:config:list --keys-only"

assert_fail "config:list --section=nonexistent fails" \
  "drush dxt:config:list --section=nonexistent"

section "Config Commands — Get"

assert_success "config:get header_top_layout returns success" \
  "drush dxt:config:get header_top_layout"

assert_has "config:get returns setting field" \
  "drush dxt:config:get header_top_layout" "setting"

assert_eq "config:get returns correct section" \
  "drush dxt:config:get header_top_layout" '.setting.section' "header"

assert_fail "config:get nonexistent_key fails" \
  "drush dxt:config:get nonexistent_key_xyz"

section "Config Commands — Set"

assert_dry_run "config:set dry-run succeeds" \
  "drush dxt:config:set header_top_layout centered --dry-run"

assert_success "config:set updates value" \
  "drush dxt:config:set header_top_layout centered"

assert_eq "config:set persists value" \
  "drush dxt:config:get header_top_layout" '.setting.value' "centered"

assert_success "config:set body_font_size numeric" \
  "drush dxt:config:set body_font_size 16"

assert_eq "config:set numeric persists" \
  "drush dxt:config:get body_font_size" '.setting.value' "16"

assert_success "config:set boolean value" \
  "drush dxt:config:set boxed_layout 0"

section "Config Commands — Export/Import"

assert_success "config:export to file succeeds" \
  "drush dxt:config:export --file=/tmp/dxt-test-export.yml"

assert_runs "exported file exists" \
  "test -f /tmp/dxt-test-export.yml"

assert_dry_run "config:import dry-run succeeds" \
  "drush dxt:config:import /tmp/dxt-test-export.yml --dry-run"

assert_success "config:import applies settings" \
  "drush dxt:config:import /tmp/dxt-test-export.yml"

assert_fail "config:import nonexistent file fails" \
  "drush dxt:config:import /tmp/nonexistent.yml"

section "Config Commands — Reset"

assert_dry_run "config:reset dry-run succeeds" \
  "drush dxt:config:reset --section=typography --dry-run"

assert_success "config:reset section succeeds" \
  "drush dxt:config:reset --section=typography"
