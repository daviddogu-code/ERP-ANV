#!/usr/bin/env node

/**
 * @file
 * Syncs settings-schema.json summary into SKILL.md files.
 *
 * Reads data/settings-schema.json and generates a condensed settings
 * reference table, then injects it between SETTINGS_SCHEMA_START/END
 * markers in both SKILL.md files.
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..', '..');
const SCHEMA_PATH = path.join(ROOT, 'data', 'settings-schema.json');
const SKILL_FILES = [
  path.join(ROOT, '.claude', 'skills', 'dxt', 'SKILL.md'),
  path.join(ROOT, '.agents', 'skills', 'dxt', 'SKILL.md'),
];

const START_MARKER = '<!-- SETTINGS_SCHEMA_START';
const END_MARKER = '<!-- SETTINGS_SCHEMA_END -->';

function loadSchema() {
  const raw = fs.readFileSync(SCHEMA_PATH, 'utf8');
  const data = JSON.parse(raw);
  delete data._meta;
  return data;
}

function buildSectionSummary(schema) {
  const sections = {};

  for (const [key, def] of Object.entries(schema)) {
    const sec = def.section;
    if (!sections[sec]) {
      sections[sec] = { keys: [], descriptions: new Set() };
    }
    sections[sec].keys.push(key);
  }

  // Build markdown table.
  const lines = [
    '## Settings Sections',
    '',
    '| Section | Key Examples | Description |',
    '|---|---|---|',
  ];

  const sectionDescriptions = {
    'layout': 'Page layout, grid, backgrounds, full-width regions',
    'header': 'Header layout, sticky, colors, mobile, menu styling',
    'page-title': 'Page title region, breadcrumbs, background',
    'colors': 'Color scheme and palette values',
    'fonts': 'Font families and CSS selectors',
    'typography': 'Font sizes, line heights, scale, dividers',
    'block-design': 'Block styling, titles, dividers, regions',
    'custom-css': 'Sitewide CSS and JavaScript injection',
  };

  for (const [sec, info] of Object.entries(sections)) {
    const examples = info.keys.slice(0, 3).join(', ');
    const suffix = info.keys.length > 3 ? ', ...' : '';
    const desc = sectionDescriptions[sec] || '';
    lines.push(`| ${sec} | ${examples}${suffix} | ${desc} |`);
  }

  return lines.join('\n');
}

function injectIntoSkill(filePath, content) {
  if (!fs.existsSync(filePath)) {
    console.log(`  Skipping ${filePath} (not found)`);
    return;
  }

  const skill = fs.readFileSync(filePath, 'utf8');
  const startIdx = skill.indexOf(START_MARKER);
  const endIdx = skill.indexOf(END_MARKER);

  if (startIdx === -1 || endIdx === -1) {
    console.log(`  Skipping ${filePath} (markers not found)`);
    return;
  }

  const before = skill.substring(0, startIdx);
  const after = skill.substring(endIdx + END_MARKER.length);
  const startLine = `${START_MARKER} (auto-generated — do not edit manually) -->`;

  const updated = `${before}${startLine}\n${content}\n${END_MARKER}${after}`;
  fs.writeFileSync(filePath, updated, 'utf8');
  console.log(`  Updated ${path.relative(ROOT, filePath)}`);
}

// Main.
console.log('Syncing settings schema to SKILL.md files...');
const schema = loadSchema();
const summary = buildSectionSummary(schema);

for (const skillFile of SKILL_FILES) {
  injectIntoSkill(skillFile, summary);
}

console.log('Done.');
