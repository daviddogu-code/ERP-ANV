#!/bin/bash
# E2E Test Helper Functions for DXPR Theme Drush Commands
# Modeled on dxpr_builder's e2e helpers.

# Disable exit on error for test assertions.
set +e

PASS=0
FAIL=0

# Colors.
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

# Assert YAML field equals expected value.
assert_eq() {
  local desc="$1" cmd="$2" yq_filter="$3" expected="$4"
  local result
  result=$(eval "$cmd" 2>/dev/null | yq -r "$yq_filter" 2>/dev/null) || true
  if [ "$result" = "$expected" ]; then
    echo -e "${GREEN}✓${NC} $desc"
    ((PASS++))
  else
    echo -e "${RED}✗${NC} $desc"
    echo "  Expected: $expected"
    echo "  Got: $result"
    ((FAIL++))
  fi
}

# Assert command returns success: true.
assert_success() {
  local desc="$1" cmd="$2"
  local result
  result=$(eval "$cmd" 2>/dev/null | yq -r ".success" 2>/dev/null) || true
  if [ "$result" = "true" ]; then
    echo -e "${GREEN}✓${NC} $desc"
    ((PASS++))
  else
    echo -e "${RED}✗${NC} $desc (expected success: true)"
    ((FAIL++))
  fi
}

# Assert command fails (either exits non-zero OR returns success: false).
assert_fail() {
  local desc="$1" cmd="$2"
  local exit_code result
  result=$(eval "$cmd" 2>/dev/null)
  exit_code=$?

  if [ $exit_code -ne 0 ]; then
    echo -e "${GREEN}✓${NC} $desc"
    ((PASS++))
    return
  fi

  local success
  success=$(echo "$result" | yq -r ".success" 2>/dev/null) || true
  if [ "$success" = "false" ]; then
    echo -e "${GREEN}✓${NC} $desc"
    ((PASS++))
  else
    echo -e "${RED}✗${NC} $desc (expected failure)"
    ((FAIL++))
  fi
}

# Assert YAML output has a field.
assert_has() {
  local desc="$1" cmd="$2" field="$3"
  local result
  result=$(eval "$cmd" 2>/dev/null | yq -e "has(\"$field\")" 2>/dev/null) \
    || true
  if [ "$result" = "true" ]; then
    echo -e "${GREEN}✓${NC} $desc"
    ((PASS++))
  else
    echo -e "${RED}✗${NC} $desc (missing field: $field)"
    ((FAIL++))
  fi
}

# Assert array/items is not empty.
assert_not_empty() {
  local desc="$1" cmd="$2"
  local result output
  output=$(eval "$cmd" 2>/dev/null)
  result=$(echo "$output" | yq '.items | length' 2>/dev/null)
  if [ -z "$result" ] || [ "$result" = "null" ] || [ "$result" = "0" ]; then
    result=$(echo "$output" | yq 'length' 2>/dev/null) || result=0
  fi
  if [ -z "$result" ] || [ "$result" = "null" ]; then
    result=0
  fi
  if [ "$result" -gt 0 ] 2>/dev/null; then
    echo -e "${GREEN}✓${NC} $desc"
    ((PASS++))
  else
    echo -e "${RED}✗${NC} $desc (array is empty)"
    ((FAIL++))
  fi
}

# Assert command exits with 0.
assert_runs() {
  local desc="$1" cmd="$2"
  if eval "$cmd" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} $desc"
    ((PASS++))
  else
    echo -e "${RED}✗${NC} $desc (command failed)"
    ((FAIL++))
  fi
}

# Assert dry-run returns dry_run: true.
assert_dry_run() {
  local desc="$1" cmd="$2"
  local output success dry_run
  output=$(eval "$cmd" 2>/dev/null) || true
  success=$(echo "$output" | yq -r ".success" 2>/dev/null) || true
  dry_run=$(echo "$output" | yq -r ".dry_run" 2>/dev/null) || true
  if [ "$success" = "true" ] && [ "$dry_run" = "true" ]; then
    echo -e "${GREEN}✓${NC} $desc"
    ((PASS++))
  else
    echo -e "${RED}✗${NC} $desc (expected success+dry_run: true)"
    ((FAIL++))
  fi
}

# Print test section header.
section() {
  echo ""
  echo "=== $1 ==="
}

# Drush command shorthand.
drush() {
  ./vendor/bin/drush "$@"
}
