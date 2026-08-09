# Roadmap — Orders Module (next phase, after Inventory/Raw Materials)

> Source: ERP Order Workflow Schematic image shared by user (2026-08-09), based on
> user concept image "Image_1784.png". Title on diagram: "ACTA TOTAL ENTERPRISE ERP -
> ORDERS MODULE HUB".

## Sequencing

This work starts **after** `tec_inventory` (raw materials, units of measure, costing,
naming matrix in `docs/material_inventory_naming_matrix.md`) is fixed and solid, since
the Orders module's Stock Control / Super BOM logic depends on accurate inventory data.

## Concept: "Orders" as a central hub

```
                         [ PO ] (sent to supplier)
                            ^
                            |
                  [ Stock Control ] (SUPER BOM)
                            ^
                            |
[ Invoice ]                |                [ Production Documents ]
[ Packing List ]  <----> [ ORDERS ] ----->   (blueprints / work orders)
[ Proforma ]                |
                            v
                    [ Orders on Queue ]
                    (sequential pipeline)
```

- **Orders** is the central hub entity/module.
- **Stock Control (Super BOM):** when an order is processed, checks the BOM
  (bill of materials) against current inventory; if raw materials are insufficient,
  triggers generation of a **Purchase Order (PO)** to the supplier.
- **Commercial documents:** Invoice, Packing List, Proforma — generated from/linked to
  the order (sales-side document cycle).
- **Production Documents:** work orders / production paperwork generated from the
  order for the factory floor.
- **Orders on Queue:** orders move through a sequential pipeline/queue (likely
  production status stages).

## Dependencies on Inventory module

- Super BOM calculations require reliable unit-of-measure conversions
  (`purchase_unit_of_measure` -> `inventory_unit_of_measure` -> `consumption_unit_of_measure`)
  and accurate costing fields — see `docs/material_inventory_naming_matrix.md`.
- Stock level tracking must be based on `unit_of_stock` per the rule already documented.

## Open questions (to revisit when this phase starts)

- Is "Orders" a new ECK entity type / content type, or does it extend `tec_crm`?
- What are the statuses/stages in "Orders on Queue"?
- Which documents (Invoice, Packing List, Proforma, Production Documents, PO) are
  generated as PDFs vs. Drupal entities/views?
