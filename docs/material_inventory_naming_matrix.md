# Material Inventory — Naming Matrix & Business Rules

> Source: technical notes derived from explanatory video (2026-08-09).
> Purpose: reference for renaming/aligning fields in the `tec_inventory` custom module
> (taxonomy vocabulary `tec_inventory`, bundle `tec_inventory`) and for implementing
> validation rules described below.

## Naming Matrix

| Current UI Field / Target Concept | Database / Technical Nomenclature Required |
| :--------------------------------- | :------------------------------------------ |
| Inventory Unit of Measure          | `inventory_unit_of_measure`                 |
| Cost of Purchasing Unit            | `purchase_cost`                             |
| Initial Inventory Stock            | `purchase_inventory`                        |
| Formulas & Calculations            | `formula_calculations`                      |

## Core Database Relationships & System Rules

- **Mandatory relationship field:** `supplier_id` is strictly mandatory. Frontend and
  backend validation must block database insertion and require the user to choose an
  item if this selection is missing.
- **Stock tracking alignment:** when tracking inventory levels or adjusting quantities,
  the value must explicitly map to the tracking **unit of stock** (`unit_of_stock`),
  rather than the purchasing or consumption units.
- **Downstream production logic:** the pricing math and conversion formulas defined in
  this schema (e.g. converting purchasing rolls to use-centimeters) are explicitly
  built to feed into downstream material requirement calculations and factory
  production cost tracking modules.

## Mapping to current `tec_inventory` fields (as found in live config, 2026-08-09)

| Target concept | Existing Drupal field (machine name) | Notes / open questions |
| :-- | :-- | :-- |
| `inventory_unit_of_measure` | `field_tec_uos` (label "Unit of Stock (UoS)") | Rename label to "Inventory Unit of Measure"; used as the source of truth for stock tracking per the rule above. |
| `purchase_cost` | `field_tec_cost` (label "Cost") | Already required; rename label to "Cost of Purchasing Unit" / "Purchase Cost". |
| `purchase_inventory` | ⚠️ unclear — closest candidates: `field_tec_stock_level` (hidden decimal) | Need to confirm: is "Initial Inventory Stock" the opening/starting stock quantity when a material is first created? If so this is a new concept, `field_tec_stock_level` is currently unused/hidden and may be repurposed. |
| `formula_calculations` | `field_tec_split_into` (label "UoS to UoU conversion formula") + `field_tec_units`/`field_uop_to_uos` (UoP↔UoS) | Multiple overlapping/duplicate fields exist today (see below) — needs consolidation before renaming. |
| `supplier_id` (mandatory) | `field_tec_vendor` (currently used, required) vs `field_tec_suppliers` (exists, unused, multi-value) | Must confirm which one is canonical before enforcing "mandatory, blocks insert" validation. |

### Known duplicate/dead fields found in current schema

- `field_tec_units` (label: "Nr. of units UoP to UoS conversion formula") — **active**, conditionally required via `field_tec_package_units` checkbox.
- `field_uop_to_uos` (label: "UoP to UoS conversion formula") — **unused/hidden**, likely intended replacement for `field_tec_units`.
- `field_tec_split_into` (label: "UoS to UoU conversion formula") — **active**, conditionally required via `field_tec_split_inventory` checkbox.
- `field_tec_uos_uou` (decimal, hidden) — **unused/hidden**, likely intended replacement for `field_tec_split_into`.

These need to be reconciled (pick one canonical field per conversion, migrate any data, remove the other) before final renaming to `formula_calculations`-style nomenclature.
