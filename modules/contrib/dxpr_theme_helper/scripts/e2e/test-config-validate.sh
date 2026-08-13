#!/bin/bash
# Config Validation Tests

section "Config Validation"

# Invalid enum value.
assert_fail "config:set rejects invalid enum" \
  "drush dxt:config:set header_top_layout fancy_layout"

# Out of range numeric.
assert_fail "config:set rejects out-of-range value" \
  "drush dxt:config:set body_font_size 999"

# Below range.
assert_fail "config:set rejects below-range value" \
  "drush dxt:config:set body_font_size 2"

# Invalid color.
assert_fail "config:set rejects invalid color" \
  "drush dxt:config:set boxed_layout_boxbg not-a-color"

# Valid color accepted.
assert_success "config:set accepts valid color" \
  "drush dxt:config:set boxed_layout_boxbg '#ff0000'"

# Unknown key.
assert_fail "config:set rejects unknown key" \
  "drush dxt:config:set totally_fake_key some_value"

# Valid boolean values.
assert_success "config:set accepts boolean 1" \
  "drush dxt:config:set sticky_footer 1"

assert_success "config:set accepts boolean 0" \
  "drush dxt:config:set sticky_footer 0"

# Valid radios option.
assert_success "config:set accepts valid radios option" \
  "drush dxt:config:set background_image_style contain"

assert_fail "config:set rejects invalid radios option" \
  "drush dxt:config:set background_image_style stretch"
