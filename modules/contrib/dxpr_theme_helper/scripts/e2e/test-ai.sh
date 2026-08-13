#!/bin/bash
# AI Commands Tests (dry-run only — AI provider may not be configured)

section "AI Commands"

# Palette generation — expect graceful error (no AI module in test env).
assert_fail "generate:palette fails without AI module" \
  "drush dxt:generate:palette 'Test palette'"

# Font generation — expect graceful error.
assert_fail "generate:fonts fails without AI module" \
  "drush dxt:generate:fonts 'Test fonts'"
