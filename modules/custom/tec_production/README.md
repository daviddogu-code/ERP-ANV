# TEC Production

Production planning module for the Acta Total Enterprise ERP.
Replaces the Google Sheets files: `Orders on Queue.xlsx`, `COUNT PRODUCTION 2026.xlsx`
and (later) `Stock control.xlsx` (sheet "Stock new").

## Build plan (4 phases)

| Phase | URL | Status |
|---|---|---|
| 1. Orders on Queue | `/o/queue` | DONE |
| 2. Daily Production Log | `/production/log` | DONE |
| 3. Production Report (monthly, ex COUNT PRODUCTION) | `/production/report` | DONE |
| 4. Stock Control matrix (ex "Stock new") | `/stock` | DONE |

Textile queue (`/o/queue/textile`) may be added later: same screen, separate
capacity parameters.

## Business rules (agreed with the user)

### Queue (phase 1)
- Rows = sales orders (`tec_order`, bundle `tec_sales_order`) with status
  Open .. Ready for Delivery. EXW: the order stays on the queue until
  Shipped/Delivered (or Cancelled); only those leave the queue.
- Status split after QC: `completed` (mfg done — invoice + packing list
  admin period) then `ready_for_delivery` (admin done, waiting to ship).
  Both stay on the queue; neither consumes manufacturing capacity or the
  deadline chain (deadline shows —).
- **Bottom row = produced first, newest orders at the top** (same layout as
  the Google Sheet; deadlines grow upward).
- Grey rows = status `draft` ("Open"): order placed but deposit not paid.
- Forward-only status advance on the queue: each row shows a button to the
  **next** status only (e.g. Completed → Ready, Ready → Shipped). No
  dropdown, no downgrade. Going backwards is done on the order edit form.
  Advancing to Shipped/Delivered removes the order from the queue.
- Columns per product category = sum of line item quantities grouped by the
  product's `field_tec_product_type` term.
- `V` (Total) = sum of line item quantities of the order.
- `X` (Produced) = `field_tec_produced` on the order. Read-only on the
  queue for everyone: it is calculated exclusively from the Daily
  Production Log (initial balances for legacy orders are registered as
  back-dated log entries). The queue form only ever saves positions, so a
  stale browser tab can never overwrite log data.
- `Y` (Remaining) = `V − X`. Only Y consumes capacity.
- Capacity: `pieces/day = staff × factor / 8 × (8 + overtime_hours)`
  (sheet: `42.6 × 1.5 / 8 × (8+0) = 63.9`). `pieces/month = pieces/day × 30`.
- Deadline chain, top-down: `deadline(row) = deadline(previous counted row) +
  Y/pieces_per_day`, anchored at today.
- Projection toggle `include_open`: when off, grey (Open) orders do not consume
  capacity in the chain (deadline shows —). Post-mfg statuses never consume
  capacity and are excluded from backlog months. Both backlog indicators
  (confirmed / with pipeline, in months) are always displayed.
- Queue position = `field_tec_queue_position` (integer, hidden from order
  forms). Drag & drop via core tabledrag; positions saved on Save.
  JS recalculates the deadline column live while dragging.

### Production log (phase 2)
- Entity `tec_production_entry` (custom content entity, base fields only):
  entry_date (Production Date) + order_id + line_item_id + quantity + uid +
  created (Logged = when Save was clicked).
- Entry form `/production/log`: pick date + order (active queue orders,
  next-to-produce first) -> grid of its line items (product / color / size /
  ordered / produced / remaining / today). Multiple orders per day supported.
- On entry insert/update/delete: `field_tec_produced` (X on the queue) of the
  order is recomputed as SUM of its entries (hooks in tec_production.module).
  No entries → X = 0. Initial balances for legacy orders are registered as
  back-dated log entries, never typed on the queue.
- Latest 30 entries are listed under the form with Delete links
  (`/production/log/{id}/delete`, core confirm form).
- Permission: `register tec production`.
- Real capacity (actual pieces/day) can be measured from log history (phase 3).
- **Images:** log grid uses the order-page pattern (`small_40x40` + `large`
  hover popup). Toolkit is **GD** (Drupal default).
- **2026-08-11 incident (`ERR_CONNECTION_RESET` / site down):** root cause was
  Drupal's stock `.htaccess` line `php_value assert.active 0` — deprecated in
  PHP 8.3, its per-request application under mod_php (threaded, Windows)
  corrupted the Apache worker, segfaulting every 2nd request (0xC0000005 in
  `VCRUNTIME140.dll`). Fixed by removing that block from `.htaccess`
  (upstream: drupal.org/i/3379901). GD was never the problem; the ImageMagick
  toolkit detour was reverted and cleaned up on 2026-08-11: modules
  imagemagick / file_mdm(+exif/font) / sophron / image_effects (unused, no
  image style depended on it) uninstalled, `drupal/imagemagick` removed from
  composer (`drupal/file_mdm` package remains as a dependency of
  `drupal/image_effects`, module OFF). Binaries remain in
  `C:\laragon\bin\imagemagick\` if ever needed.
  **Composer scaffold warning:** removing/requiring packages regenerates
  `.htaccess` and restores the crashing `assert.active` block; scaffold is
  now told to skip `.htaccess` (`file-mapping` false in composer.json).
  Composer also could not re-apply
  `patches/ief-close-all-forms-null-entities.patch` (no git/patch tool on
  this machine) — the one-line fix was re-applied manually to
  `inline_entity_form.module`; re-check after any composer update of that
  module. Use `composer install` / targeted `composer require|remove pkg`
  only: full `composer update` (and `update --lock`) does NOT resolve on
  this legacy graph (pre-existing: core is pinned 10.2.4 but dev-branch
  deps now want ^10.3), and it deletes patched packages before failing.
  The "lock file not up to date" warning is that edit's cosmetic cost —
  install still works from the lock.

### Report (phase 3)
- `/production/report?month=YYYY-MM` (permission `view tec production
  report`). Month view: rows = days (Sundays shaded, today highlighted),
  columns = product categories with activity, in the queue sheet order;
  TOTAL row and column. Clicking a day with data expands an order /
  material / color / size breakdown (canonical resolution shared with the
  log via `LineItemDisplay`).
- Cards: month total, days with production, real capacity (pieces per
  active day, measured) vs planned capacity (queue parameters). Use the
  comparison to calibrate the queue settings manually.
- Pure read-only query over phase 2 entries; no writes.

### Stock control (phase 4)
- `/stock` (permission `access tec stock control`): matrix materials ×
  queued orders, replaces the sheet "Stock new".
- **Rows** = every material referenced by the BoM snapshots
  (`tec_line_item_bom_item`) of the orders currently on the queue, sorted
  by name. **Order columns** use the same statuses and ordering as
  `/o/queue`: left = next to produce (bottom of the queue page).
- Fixed columns: Image | Stock (UoS, read-only; ± opens the mutation modal) |
  Material | Supplier (link to the CRM page) | On order (UoP) |
  Projected (UoP) | Projected (UoS) | Required (UoU). Image + Stock +
  Material are frozen left (sticky) while scrolling horizontally; header
  row + bottom fixed
  scrollbar use the same Google Sheets pattern as /o/queue.
- Per order: narrow ☐ (header checkbox = master for the column) + qty cell.
  Always ☐ + number (0 when the order does not use the material). BoM
  snapshot quantities are already line-item totals (verified against real
  orders), so they are summed directly — no extra multiplication.
- **Checkbox = material prepared / set aside for that order.** It does NOT
  touch stock. Persisted in the custom table `tec_stock_check`
  (order_id + material_tid = checked; uid + changed for audit). Only
  unchecked quantities count in Required / Projected. JS previews the
  numbers live.
- **Auto-save, no Save button:** every toggle POSTs immediately to
  `/stock/check` (`StockCheckController`, permission-gated +
  `X-CSRF-Token` header). Master toggle = one batched request for the
  column. Green flash = stored; on failure the checkbox reverts and an
  error message is shown, so the screen never lies.
- Unit conversions per material: `field_tec_units` = Purchase → Inventory
  (1 UoP = X UoS), `field_tec_split_into` = Inventory → Consumption
  (1 UoS = Y UoU). Missing/zero factors fall back to 1.
  - `Required (UoU)` = Σ unchecked BoM quantities.
  - `Projected (UoS)` = stock − required / split_into. Negative = red =
    purchase signal.
  - `Projected (UoP)` = projected_uos / units + on_order.
- `On order (UoP)` = Σ `field_tec_quantity` of PO line items
  (`tec_po_line_item`) whose parent PO has `field_tec_po_status = open`.
- **Stock mutations**: the Stock number on the board is read-only. The ±
  link next to it opens `/stock/{tid}/adjust` in a modal (number field, so
  no free text; optional note). That form creates a
  `tec_inventory_transaction` (positive adds, negative subtracts) and the
  existing ECA models ("Stock manager") append it to the material and
  update `field_tec_stock_level` — same flow as "Add new stock resolutions"
  on the material edit form. The board never writes stock directly.
- When an order leaves the queue (Shipped/Cancelled) its column disappears
  from the board; its `tec_stock_check` rows stay in the table (harmless,
  auditable). Open point: a "pending close-out" view for orders that left
  with unchecked materials.

## Backlog / future ideas
- Auto-advance order status from the production log: when SUM(produced) >=
  total ordered, move the order to Quality Control/Inspection (or Completed,
  TBD with the user). Only advance active production statuses (never touch
  Completed / Shipped / Cancelled, never downgrade); never auto-revert — if
  a correction drops below 100%, warn on the log form instead. Show a
  confirmation message on save ("ACTA 26-xxx reached 100% — status changed").
- Textile queue at `/o/queue/textile` with separate capacity parameters.
- One-click "use measured capacity" button on the report to update the
  queue's capacity settings.

## Integration points
- Home tiles: nodes (tec_landing_page) whose nids are stored in
  `tec_production.settings.queue_tile_nid` / `log_tile_nid` /
  `report_tile_nid` / `stock_tile_nid`; viewing one redirects to
  `/o/queue` / `/production/log` / `/production/report` / `/stock`
  (see `QueueTileRedirectSubscriber`).
- Order fields added by this module: `field_tec_queue_position`,
  `field_tec_produced` (both on bundle `tec_sales_order`, not shown on forms).
- Custom table added by this module: `tec_stock_check` (phase 4 checkbox
  persistence, see hook_schema in tec_production.install).
- Stock adjustments create `tec_inventory_transaction` entities and rely on
  the existing ECA "Stock manager" models to update the stock level.
- Everything else is read-only over existing data (orders, line items,
  products, product type taxonomy, BoM snapshots, purchase orders).

## Revert
Uninstall the module and delete the two fields above and the landing tile
node. No existing behaviour is modified.
