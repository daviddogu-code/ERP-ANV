#!/bin/bash
# Setup AI Commands Tests

section "Setup AI Commands"

# dxt:setup-ai
assert_success "setup-ai completes successfully" \
  "drush dxt:setup-ai"

# Verify files were copied to project root.
PROJECT_ROOT=$(drush php:eval 'echo DRUPAL_ROOT;' 2>/dev/null)
# Walk up to find composer.json (project root).
while [ ! -f "$PROJECT_ROOT/composer.json" ] && [ "$PROJECT_ROOT" != "/" ]; do
  PROJECT_ROOT=$(dirname "$PROJECT_ROOT")
done

assert_runs "Claude SKILL.md exists at project root" \
  "test -f '$PROJECT_ROOT/.claude/skills/dxt/SKILL.md'"

assert_runs "Agents SKILL.md exists at project root" \
  "test -f '$PROJECT_ROOT/.agents/skills/dxt/SKILL.md'"

assert_runs "openai.yaml exists at project root" \
  "test -f '$PROJECT_ROOT/.agents/skills/dxt/agents/openai.yaml'"

# Test --host=claude only installs Claude files.
rm -rf "$PROJECT_ROOT/.claude/skills/dxt" "$PROJECT_ROOT/.agents/skills/dxt"

assert_success "setup-ai --host=claude succeeds" \
  "drush dxt:setup-ai --host=claude"

assert_runs "Claude SKILL.md installed with --host=claude" \
  "test -f '$PROJECT_ROOT/.claude/skills/dxt/SKILL.md'"

assert_fail "Agents SKILL.md not installed with --host=claude" \
  "test -f '$PROJECT_ROOT/.agents/skills/dxt/SKILL.md'"

# Cleanup.
rm -rf "$PROJECT_ROOT/.claude/skills/dxt" "$PROJECT_ROOT/.agents/skills/dxt"
