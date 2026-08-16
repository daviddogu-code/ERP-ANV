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
- Fixed columns: Stock (UoS, read-only; ± opens the mutation modal) |
  Material | Image | Supplier (link to the CRM page) | Ordered (UoP) |
  Stock after queue (UoP) | ∑ Required (UoU). Stock + Material + Image are
  frozen left (sticky) while scrolling horizontally, which is why the material
  column has a fixed width in the CSS: the sticky offsets are hand-written
  sums of the widths to their left. Header row + bottom fixed scrollbar use the
  same Google Sheets pattern as /o/queue.
- Per order: narrow ☐ (header checkbox = master for the column) + qty cell.
  Always ☐ + number (0 when the order does not use the material). BoM
  snapshot quantities are already line-item totals (verified against real
  orders), so they are summed directly — no extra multiplication.
- **Checkbox = material prepared / set aside for that order.** It does NOT
  touch stock. Persisted in the custom table `tec_stock_check`
  (order_id + material_tid = checked; uid + changed for audit). Only
  unchecked quantities count in Required / Stock after queue. JS previews the
  numbers live.
- **Auto-save, no Save button:** every toggle POSTs immediately to
  `/stock/check` (`StockCheckController`, permission-gated +
  `X-CSRF-Token` header). Master toggle = one batched request for the
  column. Green flash = stored; on failure the checkbox reverts and an
  error message is shown, so the screen never lies.
- Unit conversions per material: `field_tec_units` = Purchase → Inventory
  (1 UoP = X UoS), `field_tec_split_into` = Inventory → Consumption
  (1 UoS = Y UoU). Missing/zero factors fall back to 1.
  - `∑ Required (UoU)` = Σ unchecked BoM quantities.
  - `Stock after queue (UoP)` = (stock − required / split_into) / units.
    Negative = red = the queue needs more than the warehouse holds, and the
    figure is already the quantity to put on a purchase order (before MOQ).
    It ignores what is already on order on purpose, because production cannot
    cut a roll that is still on a lorry, so read it next to `Ordered (UoP)`
    before buying. The purchase signal proper is `/purchase`, which does
    subtract what is coming: a material whose delivery covers the gap shows red
    here and does not appear there, and both screens are right.
  - The board showed this balance in three shapes until 15 August 2026 (the
    same figure in UoS, and a `Projected (UoP)` that added the goods in
    transit). The owner cut the two extras: nobody orders in inventory units,
    and the incoming-goods question belongs to `/purchase`.
- `Ordered (UoP)` = Σ still to come on PO line items (`tec_po_line_item`) whose
  parent PO has `field_tec_po_status = open`, where "still to come" is
  `field_tec_quantity` − `field_tec_quantity_received` and zero for a line
  marked as expecting nothing more. So the figure drops as deliveries arrive
  instead of standing still until someone deletes the order. Each order it is
  waiting on is listed next to the number, linked to its receive form.
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

### Purchase list (phase 5)
- `/purchase` (permission `access tec purchase list`, granted to Manager and
  Executive but **not** to the floor supervisor: creating a purchase order
  commits money): what to buy right now, grouped by supplier.
- **Rows** = materials whose available quantity no longer covers their reorder
  point. Available = `field_tec_stock_level` (UoS) + still to come (UoP ×
  units), so something ordered this morning is not ordered again this
  afternoon, and something that has already arrived is not counted twice — it
  left the "still to come" side and turned up in the stock level.
- A material with an empty `field_tec_reorder_point` can never appear, however
  low its stock: those are listed in a collapsed section at the bottom rather
  than silently dropped, because an empty purchase list and a list of
  unwatched materials look identical from the outside.
- Columns: Buy ☐ | Material | Stock (UoS) | Ordered (UoP) | Reorder point
  (UoS) | Short by (UoS) | MOQ | Lead time | Quantity (UoP, editable) |
  Unit cost | Line total.
- **Suggested quantity** = ceil((reorder point + safety stock − available) /
  `field_tec_units`), raised to `field_tec_moq_quantity`, never below 1.
  Filling up to the reorder point alone would leave the material on the edge
  and it would be suggested again the next day. Every quantity is editable and
  every row can be unticked: the screen suggests, the buyer decides. JS keeps
  the line and supplier totals live while editing.
- **No total across suppliers**, on purpose: each supplier bills in its own
  currency (`field_tec_purchase_currency`, shown next to its total).
- **Create purchase order**, one button per supplier, writes a
  `tec_purchase_order` titled `YY-N` — the shape the ERP already uses, but not
  the older flow's count: `process_kryibry` counts published orders *of that
  supplier* and excludes the one being created, so its numbers repeat across
  suppliers and across consecutive drafts. Here N counts this year's orders and
  is bumped until the title is free. Plus owner = current user, status = the
  field default `open`, and one
  `tec_po_line_item` per ticked row carrying material, quantity (UoP) and
  price = `field_tec_cost`. The price has to be written even though the line
  total is computed from the material cost: the presave ECA `process_fpvka81`
  only computes the total when the line already carries a price. Lines are
  created first (the order requires them) and then pointed back at the order.
- `Purchasing::onOrder()` and `Purchasing::factor()` are shared with the stock
  board so both screens count incoming goods the same way.
  `Purchasing::INCOMING_STATUS` is the one place that decides which purchase
  order status counts as coming in, and it is a whitelist of one rather than
  "anything that is not closed" so that a draft status can never start counting
  by accident.
- End-to-end test: `scripts/funciona-la-lista-de-compra.php` asks for the
  page, presses the button, checks the order it wrote and deletes it again.
  The same arithmetic on the command line: `scripts/que-hay-que-comprar.php`.

### Receiving (phase 6)
Built 16 August 2026. Until then a purchase order could not stop being on its
way: the status field had one allowed value and no line recorded what had
arrived, so an ordered quantity counted as incoming forever and the board told
people to buy things that were already on the shelf.

- `/tec_order/{id}/receive` (permission `access tec stock control` — receiving
  is a stock movement with paperwork, so it rides on the board's permission
  instead of inventing one). Reached from the **Receive** button on an open
  purchase order, next to `Edit order` and `Print/PDF`, or from the order number
  shown next to `Ordered (UoP)` on `/stock`, which is the shortcut for whoever
  signs the delivery note.
- It is a **button and not the tab** it started as. The tab exists — it is a
  local task on `entity.tec_order.canonical` — but nobody can use it: DXPR lifts
  the tab strip out of the flow (`position: absolute`, centred, moved up its own
  full height, in `css/components/tabs.css`) and drops it over the sticky header,
  where it is unreadable, and the block that prints it is restricted to the
  `administrator` role, so the people who sign delivery notes would never see it
  at all. The tab is left in place because it costs nothing; the button is the
  door.
- Quantities are typed in **purchase units**, and the screen shows live what
  that becomes in **inventory units** (`field_tec_units` ×). That preview is
  there because the multiplication is the only thing on this screen that can
  fail without showing it: get the factor backwards and the warehouse receives
  a believable wrong number that nobody notices for weeks.
- Per line with a quantity, on submit:
  1. `field_tec_quantity_received` goes up.
  2. A `tec_inventory_transaction` is created for the material with the
     converted quantity. That is the only door stock has — the existing ECA
     models ("Inventory: Stock manager") move `field_tec_stock_level` from it,
     exactly as the `±` link on `/stock` does.
  3. The transaction points at the line through `field_tec_po_line`, so the
     material's history says which order brought each entry.
- **Nothing is stored for "outstanding"**, and there is no box to type it in.
  `Purchasing::outstanding()` is ordered − received, floored at zero, and zero
  for a line closed short. An ERP that lets a human type what is still coming
  ends up with a figure nobody can reconcile against the orders; Odoo and SAP
  both derive it.
- **Two statuses in the database**, `open` and `closed`. Whether a receipt was
  full, partial, short-closed or never came is derived by
  `Purchasing::receiptState()` from the lines (`none` / `partial` / `full` /
  `cancelled`). Storing that as a third status would be a second version of the
  truth and the two would drift the first time a quantity was fixed by hand.
  The order closes itself when no line has anything outstanding.
- Three decisions taken by the owner, all visible in the code:
  - A short delivery is written off with the **checkbox on its row**, not with
    a dialog at the end. Odoo asks the question once, at validation, and only
    for lines that fall short — nicer, and impossible to forget. That is a
    second form step and can be added later without changing a single stored
    value, because the data written is identical either way.
  - **Over-delivery is accepted with a warning.** The goods are physically in
    the building, and stock that lies to keep a document tidy is worse than a
    surprise.
  - The **reason for closing is optional**. Still worth writing: line items
    carry no author and no revisions, so without it the decision leaves no
    trace beyond a changed timestamp.
- **No undo.** To correct a receipt, register the difference with the `±` link
  on `/stock` and fix `Received` on the line by hand (it is on the line item
  form for exactly that).
- Screens this dragged along:
  - The purchase order's line table (`tec_order_sales_order_line_items`,
    `block_2`) gained `Received` and a `closed short` marker behind the
    quantity.
  - The buttons under that table (`attachment_6` of the same view) went through
    the same views_conditional treatment as the supplier list: open shows
    **edit, receive, print**, closed shows **print** only. Until then a closed
    order still offered edit on its own page while the supplier list already
    refused it.
  - In the supplier's order list (`tec_supplier_orders`, `block_3`) the edit
    and print buttons live inside a views_conditional that only fires when the
    status is `Open`. With one status that never showed; with two, a closed
    order would have lost both. The conditional now has its else branch:
    closed orders keep **print** (the copy for accounts) and lose **edit**.
  - The material's stock history needed nothing: the mutation note already
    reads `Received on PO 26-2, delivery note 4471-B`.

### The purchase order lists say the status (16 August 2026)
Asked for by the owner, who could not find the statuses anywhere on screen. He was
right: they were nowhere.

- `/supplier-orders` had a column headed **Order status** that was empty in every
  row, and not for lack of data. Its `default` display asked for
  `field_tec_order_status`, the status of a **sales** order. A purchase order does
  not have that field, and the page is filtered to purchase orders, so the column
  was empty a hundred per cent of the time from the day it was made. The same
  missing field fed the views_conditional behind the row buttons, so the edit
  pencil never appeared either, and the link behind it pointed at `/o/draft`, the
  sales order editor. Three symptoms, one cause: the display was copied from a
  sales order list and nobody finished changing the questions.
- The column now reads `field_tec_po_status`, and there is a second hidden copy,
  `field_tec_po_status_1`, for the conditional — views_conditional compares
  against what a field *prints*, and the visible one prints
  `<div class="Open">Open</div>`, not `Open`. Same trick `block_3` already used.
  Open rows get **edit + print**, closed rows **print** only, and the pencil goes
  to `/po/draft/{id}` with the list as its destination.
- New column **Delivery**, on `/supplier-orders` and on the supplier's own
  Purchase orders tab (`tec_orders_orders`, `block_3`): *Nothing received*,
  *Partially received*, *Fully received*, *Cancelled*. It exists because `Open`
  alone does not tell whoever chases suppliers what to chase — an order where
  nothing has turned up and one where half is already on the shelf read the same.
- It is a **views field plugin of our own**,
  `Plugin/views/field/ReceiptState.php` (`tec_purchase_receipt_state`, exposed on
  `tec_order_field_data` by `tec_production.views.inc`), because there is no field
  to point a normal column at: it calls `Purchasing::receiptState()` per row, the
  same function the board and the guardian use, so the lists cannot end up
  disagreeing with them. It adds nothing to the query and it cannot be sorted on.
  Its cache tags include the **lines**, not just the order: saving a line
  invalidates the line, and without that the word would go stale in the render
  cache.
- Only the `default` display was touched; the three blocks have their own field
  lists. The style **is** shared, so entries were only added to its column map,
  never removed: taking out the sales status entry would have cost `block_1` its
  alignment.
- Done by `scripts/arreglar-la-lista-de-pedidos-de-compra.php`, safe to run twice.

### Checkboxes were invisible, site wide (16 August 2026)
Found because the receive form's **Nothing more expected** column looked empty and
the owner asked whether a checkbox was missing. It was not missing: it was there
in the HTML, unstyled into thin air.

Bootstrap 5 draws its own box rather than the browser's — `.form-check-input` sets
`appearance: none` and paints the box with `--bs-border-color` on a fill of
`--bs-body-bg`. DXPR points `--bs-border-color` at `--dxt-color-graylighter`,
about `#ededed` in this site's palette, and `--bs-body-bg` at the page background,
which is white. Every unchecked checkbox on the site was a 13-pixel white square
with a border nobody can see, including the ones on the stock board that decide
what gets bought.

`css/dxpr-checkbox-fix.css`, attached site wide from
`tec_production_page_attachments()` next to the header fix, gives the unchecked
box a `#767676` border. Only the unchecked state: Bootstrap's checked state fills
the box and draws a white tick, and that always read fine. The receive form adds a
little on top, because Bootstrap lays the box out with a float and a negative
margin that make sense down a column of labelled fields and none in a table cell
where the header is the label.

The test now asserts the stylesheet is on the page. Existing in the HTML is not
the same as being on the screen, and this is the second time that distinction has
bitten this feature — the first was the tab nobody could read.
- End-to-end test: `scripts/funciona-la-recepcion.php` walks a real delivery
  with both of its surprises — part of one line arrives, the rest is written
  off short, and more than ordered arrives on the other — and checks the
  conversion by computing it separately from the material's factor. It undoes
  everything it creates, including the stock levels the ECA moved.
  - It counts **buttons, not occurrences of the URL**. The first version looked
    for the address anywhere in the page, found it in the invisible tab, and
    passed a feature nobody could reach. It also only looks inside
    `div.btn-default`, because Drupal computes a route's local tasks once per
    process with the access result baked in, so a test that renders the same
    page before and after closing the order sees a tab that a real request
    would not.
- The fields were created by `scripts/crear-los-campos-de-la-recepcion.php`, the
  view changes by `scripts/poner-lo-recibido-en-las-pantallas.php` and the button
  by `scripts/poner-el-boton-de-recibir-en-la-ficha.php`; all three are safe to
  run twice.

### Order numbers, in one place (16 August 2026)
Every order is now called `CODE YY-NNN`: the other party's short code, the
two-digit year, and a counter that runs **per contact** and restarts each January.
`KJ 26-001`. Asked for in these words: customer A orders and gets `A 26-001`,
customer B orders and gets `B 26-001` — not 002 — and when A comes back, `A 26-002`.

`src/OrderNumber.php` is the only place a number is decided, called from
`hook_tec_order_presave()` in `tec_production.module`, on creation only. Renaming
later would change what is printed on a document already sent to a supplier, and
the number exists precisely so both sides can quote the same one.

**Why one place.** There were three, and they disagreed:
- `PurchaseListForm::nextOrderNumber()`, counting purchase orders by title prefix
  across all suppliers, with no code and no padding: `26-4`.
- `process_kryibry`, counting rows in `tec_order_eca_count_orders:po_count`.
- `process_sclj26d`, the same through `so_count`, and the only one that had ever
  been taught the short code and the three digits.

Both ECA counts filtered to **published** orders, and both flows create orders
`published: false`. So a draft never counted: the second order for a contact was
handed the number the first one already had. Neither checked the name was free, so
a deleted order's number got issued again. Neither had a year filter, so the
counter would never have restarted in January — the `26-` in the name came from
the date while the number behind it ran on forever.

**How the counter works now.** It counts names of the exact shape already handed
out (`KJ 26-` as a title prefix), adds one, and then climbs until the name is
actually free. Counting names rather than orders ties the number to what has been
issued, so it survives an order changing hands or having its date corrected, and
it restarts by itself because the year is part of what is counted. The climb is
what makes it safe rather than merely likely: counting alone hands out a number a
deleted order already used. Two orders created in the same instant could still
race; serialising that would mean a lock on every order to protect against
something one person clicking one button cannot cause.

Draft orders (`tec_draft_order`) are deliberately not numbered. They are a staging
area that gets thrown away, and burning a number on one would leave a hole in a
customer's sequence that nobody could explain.

**The ordering trap, and why both ECA processes were re-wired.** Both created the
order, saved it, and attached the customer or supplier *afterwards*. That was
harmless while the name came later. Now the name is decided at the first save, so
an order would be born with no short code at all. The two processes now go create,
attach contact, save. The guardian asserts that order of steps, because it is the
kind of thing that gets quietly undone by someone tidying up a diagram.

**The landmine that was one click from erasing all of this.** An ECA process lives
in two config objects: `eca.eca.X` runs, and `eca.model.X` is the BPMN drawing the
visual editor shows — and the editor **regenerates the first from the second**
every time anybody presses save. The short code and the three digits existed only
in `eca.eca.process_sclj26d`. The drawing still said
`[current-date:custom:y]-[soCount]` and had no trace of the padding steps. Opening
that process in the editor and pressing save would have taken sales orders back to
`26-1`, silently.

So `scripts/quitar-la-numeracion-de-eca.php` operates on the **drawing** and then
hands it to ECA's own modeller, which regenerates the executable file from it. The
two agree now, which is the only state that does not rot. The guardian checks two
things: that neither file has a step setting the *order's* title (each still sets
its *line items'* titles, which is a different thing and has to stay), and — for
all 32 processes, not just these two — that the drawing and the executable know
the same set of steps. That general check is what would have caught this in the
first place.

`views.view.tec_order_eca_count_orders` is now dead config. It is left in place
because deleting it would strand a reference in a viewfield settings list, but
nothing uses it and nothing should: its `so_count` and `po_count` displays encode
the published-only filter that caused the repeats.

- Test: `scripts/se-numeran-los-pedidos.php` asserts the A/B/A case literally, and
  checks both routes that create orders — the flag on a contact card and the entity
  API — because for years they disagreed. It also proves the contact is attached in
  time for its code to reach the name, that a gap in the sequence makes the counter
  climb rather than repeat, and that no code leaves no dangling space: `26-001`.
- The existing orders were renamed by `scripts/renumerar-los-pedidos.php`, which
  renumbers per contact and per year from each order's own creation date, is
  idempotent, and does nothing without `--de-verdad`.

### VAT: one fact per supplier, one rate for the company (16 August 2026)
Three situations that look different are the same question. A supplier abroad
charges no Thai VAT. A small Thai supplier below the registration threshold
charges none either. A registered Thai supplier charges 7%. So there is no rule
about countries with exceptions bolted onto it; there is **one fact per
supplier**, and it is whether they are registered and charging.

That fact is `field_tec_vat_treatment` on the contact card, in the *Supplier
purchase defaults* fieldset, on both organizations and people — a supplier here
is sometimes a person, and currency, incoterm and payment terms are already on
both. Three answers: *Registered for VAT, charges it*, *Thai, not registered,
charges no VAT*, *Outside Thailand, no Thai VAT*.

**Why a short list and not a tick box.** Two of the three answers mean the same
zero, but for different reasons, and the reason is worth keeping: a small Thai
supplier may register next year, a foreign one never will, and with a tick box
nobody could tell them apart a year from now. It also means the small ones can be
listed and asked, which is exactly what the seeding script does.

**Why not derive it from the tax number.** Tempting — a Thai business registered
for VAT has one, an unregistered one does not, and `field_tec_tax_nr` is already
on the card. Dropped because a foreign supplier may well have their own country's
number sitting in it, which would invent 7% nobody is charging, and because a
registered supplier whose number was never typed in would quietly lose the VAT.
For money, a fact somebody stated beats a fact inferred.

**The rate is not on the supplier.** It is `vat_rate` in `tec_production.settings`,
one number, so the day the country changes it, it is changed once and not on three
hundred cards. Which suppliers charge it is the separate question above. It is
edited on **Configuration → TEC → Company settings** (`/admin/config/tec/company`),
and there is a link straight to it under the VAT treatment field on every supplier
card, showing the rate in force: *"VAT is currently 7% — change it"*.

**What the order keeps.** `field_tec_vat_rate` on `tec_purchase_order`, written by
`hook_tec_order_presave()` at creation from the supplier's card, and editable on
the order afterwards for a one-off. Copied rather than looked up on demand for the
same reason the order number is: an order printed in March must still say in
December what it said in March, whatever happened in between to the rate or to the
supplier's registration. Anything already filled in is left alone, because that is
somebody overriding it deliberately.

Sales orders do **not** get this field. What VAT is charged to a customer is a
different conversation and will not be solved by copying this one.

`src/Vat.php` holds all of it: the three treatments, the rate, the decision for a
given contact, and `Vat::on()` for the arithmetic — rounded there and only there,
so a screen, a printed order and whatever comes next cannot each round it their own
way and disagree by a satang.

**A card nobody has filled in yet is treated as charging VAT.** Getting it wrong in
that direction shows up: somebody sees VAT on an order that should not have it and
says so. The other way round it is a number missing from a document, which nobody
notices until the accountant does, months later.

- Built by `scripts/poner-el-iva-en-las-fichas.php` (safe to run twice).
- Seeded by `scripts/sembrar-el-iva-por-pais.php`, which reads the country code out
  of the Address field — Thai address means charging, anywhere else means not — and
  then prints the Thai ones for someone to go through and switch the small ones. It
  reports only until given `--de-verdad`, and leaves alone any card already filled
  in, so a correction survives it being run again.
- Test: `scripts/se-pone-el-iva.php` creates a supplier of each kind, checks what
  its order came out with, then makes the supplier stop charging VAT and proves the
  order already raised did not move.
- Guardian: section 7, six checks.

The rate stays hidden on the order view display, because a bare line reading
`VAT %: 7.00` is not what anyone wants to read. The figures belong together at
the foot of the page, which is the next section.

### The foot of a purchase order: subtotal, VAT, total (16 August 2026)
The rate was being stamped on every order and read by nobody. All three purchase
screens added up the lines and called the result **Total**. On a Thai supplier
that is not what gets paid, so the number on the screen and the number on the
invoice were never going to match.

The two screens the server draws — the order page and the printed order — now end
in three lines, put there by one handler written once,
`src/Plugin/views/area/VatTotals.php` (`tec_purchase_vat_totals`, registered in
`tec_production.views.inc`). It is an **area** and not a field because it says
something about the order, not about a line. The arithmetic is
`Vat::breakdown()`, which is also what the test and the guardian ask, so the
three of them cannot end up disagreeing.

Both screens used to borrow their total from `attachment_1`, an attachment
**shared with the two sales screens**. They no longer do; `attachment_1` still
serves `block_1` and `page_4` exactly as before. Sales was left alone on purpose:
what VAT is charged to a customer is a different question and copying this answer
would be guessing at it.

Three cases, because they look different:

- **Rate stamped.** `Subtotal`, `VAT 7%`, `Total`.
- **Rate stamped as zero** — supplier abroad or unregistered. The same three
  lines, with the VAT reading `VAT 0%`. A zero that is explained can be defended;
  a gap where a number should be cannot.
- **No rate at all** — the sixteen orders raised before any of this existed. The
  single plain `Total` they have always printed. They are not given a rate now:
  putting a VAT line on a paper already sent to a supplier is worse than leaving
  it off.

**The draft is different, and had to be.** `po/draft/%` is a form, and the point
of it is that the figures move as quantities are typed, before anything is saved.
So its foot is added up in the browser by the injected script, which had no way
of knowing the rate: it is on the order, not on any row in front of it.
`tec_production_views_pre_render()` hands it over in `drupalSettings`, and only
when the order really is a purchase order with a rate.

That guard matters because **the same script runs on the sales draft**. It asks
for a rate, and when there is none it draws the single `Grand Total` it always
drew. So purchases gained VAT and sales has not noticed.

The VAT is rounded to the satang before being added, in the browser exactly as in
`Vat::on()`. Add first and round after and the draft and the printed order end up
a coin apart on the way to the supplier.

- Built by `scripts/poner-el-iva-en-el-pie.php` (the view) and
  `scripts/el-borrador-suma-el-iva.php` (the script). Both safe to run twice.
- Test: `scripts/se-suma-el-iva.php` — a Thai supplier and a foreign one, both
  screens read as a person reads them, the rate reaching the purchase draft and
  not the sales one, and the proforma proved untouched.
- Guardian: section 9, ten checks.

### The draft and the order page read alike (16 August 2026)
They were the same information written two ways. The same column was **Unit of
Purchase (UoP)** on the draft and **Unit** on the order page; the cost was **Cost
by UoP** and **Cost per UoP**; the quantity, **Quantity** and **Qty.** Anyone
looking at both had to translate, and translating is where people slip.

Both now read: `Item | Picture | Material name | Quantity | Purchase UoM |
Purchase Cost | Sub total`, and the order page continues with `Received` and its
*closed short* marker, which belong after everything else because they are what
gets looked at once goods arrive, not while the order is being read.

Money is written the same way too, which took one non-obvious fix. The baht
symbol is not put there by the view: it is part of the field, and the view merely
honours it. But **Sub total on the order page is not a field** — it is a sum the
view works out, `@field_tec_quantity * @field_tec_cost` — so there it has to be
written in by hand. It was printing `3,998.5` next to a `฿ 799.70`.

Worth knowing, and not changed: that makes the order page the only screen whose
line total is recalculated from the current cost rather than read from the total
ECA stamped on the line. The footer sums the stamped ones. Today they agree; if a
material's purchase cost is ever edited after an order is raised, the column and
the footer below it would disagree on the same page.

> Closed the same night, and it was worse than this paragraph says: the stored
> total was being rewritten too. See *An issued purchase order is worth what it
> was worth* below.

Two presentation fixes on the draft, both in
`asset_injector.css.tec_excel_lover_orders`:

- The quantity box was the full width of its cell for the sake of four digits.
- **Quantity** was printed above every box, which the column heading already
  says. The rule that hid it had been there all along but caught only the sales
  draft: Views suffixes the second copy of a field with `-1`, and the purchase
  draft is the second.

- Built by `scripts/las-dos-pantallas-de-compra-se-parecen.php`, which refuses to
  reorder rather than guess if the screen has gained or lost a column.
- Guardian: in section 9 — the two screens' headings compared name by name,
  `Received` last, and the two CSS rules still there.

**The printout followed the same night.** `po/%/print` still said `Qty.`,
`Price` and `Unit`, and — less visible and more confusing — was in a different
order: quantity before the material, price before the unit. Two moves, not three
renames, which only became apparent with the three screens side by side in a
table. It now reads exactly as the draft does, same seven columns in the same
places, and `Purchase Cost` is right-aligned; it had no alignment at all, so
price sat left and `Sub total` right in the same table.

`Purchase UoM` is deliberately left unaligned, which is how it is on the other
two. Centring it would look better on paper, and the point of this is that the
three read alike rather than that the printout goes its own way.

- `scripts/el-impreso-se-lee-como-el-borrador.php`. Before moving anything it
  checks that no column would end up reading one placed after it: Views only
  lets a field read fields declared before it, and when that breaks it does not
  error — the column comes out blank and nobody notices until a supplier does.
  `Picture` is drawn from the two hidden image columns, so it is the one at risk.
- Guardian: one more check in section 9, the printout compared against the draft
  column by column.

### An issued purchase order is worth what it was worth (16 August 2026)
Raise an order in January for 100 at 10. In February the supplier puts the
material up to 15. In March, open the January order: it said 15, and 1,500.

Not as a display slip — **the stored total was rewritten too**. The ECA that
totals a purchase line multiplied quantity by whatever the material cost *at that
moment*, and it runs on every save. Anything touching the line repriced a closed
order, and **receiving goods saves the line**, so a lorry arriving in April was
enough to change the price of a January order with nobody touching it.

The odd part is that the flow already assumed the opposite: it computes nothing
unless the line has a price of its own — there is a condition at the entrance
checking exactly that. Somebody built the gate for the line's price and then
multiplied by something else inside. Sales never had the problem:
`process_lvy385w` always multiplied by `[entity:field_tec_price:value]`.

**The real fault was one step further back.** Twenty-nine lines carried a price
that was not a purchase price at all: `0.02` on a material bought at 80, `0.30`
on one bought at 45. Not noise — exactly 80 ÷ 4000 and 45 ÷ 150, each material's
two conversion factors. `process_kryibry`, which creates an order from a supplier
card, stamped `[inventoryItem:field_tec_price]`, and on a material
**`field_tec_price` is the cost per consumption unit**; the purchase cost is
`field_tec_cost`. The step was called *Set Sales price* inside a purchase flow.

Nobody had noticed because recalculating from the material covered it up. The
moment the line's price starts to count, that 800 order would have become 2 baht
on its next save — so fixing the multiplication alone would have been worse than
leaving it alone.

What changed:

- `process_fpvka81` multiplies by `[entity:field_tec_price:value]`, like sales.
- `process_kryibry` stamps `[inventoryItem:field_tec_cost]`. `PurchaseListForm`,
  which builds its lines in PHP rather than ECA, was already right.
- **Both BPMN models as well as both executables.** Open the modeller and save
  and the executable is regenerated from the drawing; leaving the old token there
  leaves the trap re-armed.
- `Purchase Cost` on both screens reads the line's price. `Sub total` on the
  order page is now the stored total, which the printout and the footer already
  read. All three purchase screens finally read the same two fields.

Existing lines were repaired by
`scripts/arreglar-los-precios-de-las-lineas.php` (dry run by default, `-- aplicar`
to commit) on two rules: a line with a total already shown takes price = total ÷
quantity, honouring the money the order has been showing since it was raised; a
line with no total is a half-written draft and takes today's purchase cost. All
seven of the first kind landed **exactly** on the material's purchase cost, and
no order changed value.

- Test: `scripts/el-precio-no-se-mueve.php` walks that January-to-April story
  with those figures, receipt included, checking all three screens and the
  footer. Its material has a consumption cost a hundred times smaller than its
  purchase cost on purpose: confuse the two again and the first figure shows it.
- Guardian: section 10 — both tokens, both drawings, the purchase list form, that
  no purchase screen reads the current material cost, and the one that actually
  matters: **every stored line is worth its own price times its own quantity**.

### Company settings, and the settings that had no screen (16 August 2026)
`/admin/config/tec/company`, `src/Form/CompanySettingsForm.php`. Two things on it:
the VAT rate, and which page each home tile opens.

Both were only reachable from a command line before. That is fine for whoever
builds the ERP and no use at all to whoever runs the company, which is the person
who finds out the rate has changed.

It sits under **Configuration** rather than beside the `/admin/tec/*` screens
because those are bare paths with no menu entry anywhere, so a link into that
family would arrive with a breadcrumb leading nowhere. The `/admin/config/tec`
section also gives the settings that come next — currency, company address — a
place to be.

The permission is its own, `administer tec company settings`, held by
`tec_manager` and `tec_executive`. Using Drupal's `administer site configuration`
would have meant handing someone the entire site so they could change one number.

**What deliberately did not move.** The three capacity settings stay on the queue
screen. They are read next to the figures they move, and filing them behind a menu
would be tidier and worse.

**The tile pairing is stated once.** `QueueTileRedirectSubscriber::TILES` maps each
setting to the route its icon opens; the subscriber redirects with it and the form
builds itself from it, labelling each row with the screen's own `_title`. Two
copies of that list would drift, and the way that failure shows up is an icon
quietly opening the wrong screen.

**`tec_production.settings` finally has a schema.** It never had one — the module
has no `config/schema` directory before this. It did not matter while nothing wrote
those settings through a form; a `ConfigFormBase` over schemaless config complains,
and Drupal 11 is stricter about it than 10 was. All eleven keys are described.

- Test: `scripts/se-tocan-los-ajustes.php` checks the three ways this could quietly
  stop working — the page stops loading, the permission stops holding anyone out,
  saving stops writing — plus that saving does not unhook the five home tiles, and
  that the link on the supplier card quotes the live rate rather than a hardcoded
  one. It puts the rate back as it found it, so it is safe to run on a live site.
- Guardian: five more checks in section 7.

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
  `report_tile_nid` / `stock_tile_nid` / `purchase_tile_nid`; viewing one
  redirects to `/o/queue` / `/production/log` / `/production/report` /
  `/stock` / `/purchase` (see `QueueTileRedirectSubscriber`). The tiles
  themselves link straight to those paths through their `field_tec_target`,
  so the redirect only catches someone opening the node itself.
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

---

# Deployment procedure

Validated on 2026-08-12 by rebuilding the whole ERP from scratch in a
throwaway directory (`tec-ensayo`) using nothing but the three ingredients
below. The rebuilt site served `/`, `/user/login`, `/o/queue`, `/stock` and
`/production/log` with real data and zero PHP errors, so the ingredients are
sufficient — but only if the verification steps here are followed, because
`composer install` alone does **not** produce a correct tree (see "Traps").

**Executed for real the same day** onto `erp-anv-sgp1` (DigitalOcean Singapore,
Ubuntu 24.04, PHP 8.3.33 FPM, MySQL 8.4.11, Apache 2.4.58), serving
`https://erp.anvfightgear.com`. Total time from first `scp` to a working HTTPS
login page: about 40 minutes. Linux behaved far better than the Windows
rehearsal — Composer needed a single pass, left no empty package directories
and applied the patch by itself — but two new traps appeared that the rehearsal
could not have found, both listed below (8 and 9). Traps 1–3 did **not**
reproduce on Linux; keep the verification steps anyway, since they cost seconds
and their failure mode is silent.

## Ingredients

| Ingredient | Contains | Notes |
|---|---|---|
| `github.com/daviddogu-code/ERP-ANV`, branch `main` | everything except `/core`, `/vendor`, and the two runtime file directories | ~17,500 files, 43 MB |
| database dump | the `actatec` schema | 460 tables |
| project archive | `sites/default/files` **and** `sites/default/private` | 2,566 + 1,384 files; build it with `tar`, never `Compress-Archive` — see trap 8 |

The repo carries `config/sync` (1,097 files), `libraries/` (select2,
tiny-slider, swiffy-slider, jquery-ui-touch-punch), the patch in `patches/`,
and the four contrib modules Composer does not manage (`pdf_serialization`,
`views_entity_form_field`, `quicktabs`, `integer_to_decimal`). All of these
were confirmed to arrive intact.

`sites/default/private` is **not** in the repo and is easy to forget. It holds
1,384 real files (dated folders 2024-05 … 2026-08, `feeds`, `filefield_paths`,
`file_resup_temporary`). Restore it or private-file downloads break.

## Steps

1. `git clone` the repo into the target directory.
2. `composer install` — never `composer update`, see the note in the
   2026-08-11 incident above.
3. **Verify the install and repeat if needed** (see "Traps" 1 and 2):

   ```bash
   # every package directory must exist and be non-empty
   ls modules/contrib | wc -l   # expect 156, and none of them empty
   ls themes/contrib  | wc -l   # expect 3
   # truncated files
   find core -type f -empty | wc -l   # expect exactly 37
   ```

   Those 37 empty files in `core` are legitimate: every one lives under a
   `tests/fixtures/` directory and is empty on purpose (`HtaccessTest`
   fixtures, `invalid-img-zero-size.png`, three empty `.po` files). A higher
   number, or any empty file outside `tests/fixtures/`, means trap 1. The fast
   way to tell the two apart is to check a file that must never be empty:
   `core/misc/drupal.js` (21 KB) and
   `core/assets/vendor/ckeditor5/ckeditor5-dll/ckeditor5-dll.js` (762 KB).

   Re-run `composer install` until the counts stop changing. The Windows
   rehearsal needed three passes; on Linux one is normally enough, but the
   check costs nothing.
4. **Apply the inline_entity_form patch manually and verify it** (see "Traps"
   3 — Composer will report success while silently skipping it):

   ```bash
   cd modules/contrib/inline_entity_form
   patch -p1 --no-backup-if-mismatch -i ../../../patches/ief-close-all-forms-null-entities.patch
   grep -c '?? \[\]' inline_entity_form.module   # must print 1
   ```
5. Create the database and import the dump.
6. Restore the two runtime directories, then **count what actually arrived**
   (trap 8):

   ```bash
   tar -xzf archive.tar.gz -C /tmp/staging
   mv /tmp/staging/files   /var/www/erp/sites/default/files
   mv /tmp/staging/private /var/www/erp-private       # outside the docroot
   find /var/www/erp/sites/default/files -type f | wc -l   # expect ~2,566
   find /var/www/erp-private             -type f | wc -l   # expect 1,384
   chown -R www-data:www-data /var/www/erp/sites/default/files /var/www/erp-private
   find … -type d -exec chmod 755 {} + ; find … -type f -exec chmod 644 {} +
   ```

   Only these two trees belong to the web server user. The code stays owned by
   the deploy user so a compromised PHP process cannot rewrite it.
7. Write a production `sites/default/settings.php`. Copy the active lines from
   the development file (everything that is not a comment — there are only
   about twenty) and change five things:
   - `$databases['default']['default']` — the dedicated database user, never
     `root`. Generate the password on the server and keep it out of any chat
     window or repository.
   - `$settings['trusted_host_patterns']` — the production domain only.
     Without it Drupal answers **400 Bad Request** to every request.
   - `$settings['rebuild_access']` — **FALSE**. It is `TRUE` in the development
     file, which lets anyone run `rebuild.php`.
   - `$settings['file_private_path']` — an **absolute path outside the document
     root**, e.g. `/var/www/erp-private`. The development value
     (`sites/default/private`) sits inside the tree Apache serves and must not
     ship. Confirm with `drush status`, which prints "Files, Private".
   - `$config['system.logging']['error_level'] = 'hide'` — stack traces belong
     in the log, not on the customer's screen.

   `$settings['hash_salt']` can be regenerated (`openssl rand -base64 55`) on a
   fresh install; it only invalidates sessions and pending one-time links, and
   a value that has lived in a repository is better replaced. Leave
   `config_sync_directory` (`config/sync`) alone — it is relative, correct, and
   protected by Drupal's own `.htaccess`.

   Then lock the file down: `chown root:www-data`, `chmod 640`. Verify from
   outside that it returns 403.
8. Point the web server at the project root. This is a
   `drupal/legacy-project` layout: the document root **is** the repository
   root, not a `web/` subdirectory. Set `AllowOverride All`, or Drupal's
   `.htaccess` — the thing protecting `config/sync` and every `.php` in
   `files/` — is ignored entirely. Add the deny rules from trap 9.
9. `drush cr`, then verify (see checklist).

## Traps found during the rehearsal

1. **A crashed `composer install` poisons the cache silently.** The first pass
   died on a corrupt download (`'…tmp-….zip' is a corrupted zip archive (0
   bytes)`) and cached the damaged archive. Every later pass reused it and
   extracted **113 files of `drupal/core` as 0 bytes** — including
   `assets/vendor/backbone/backbone.js` (79 KB) and three CKEditor 5 plugins,
   which breaks the admin UI, plus every `assets/scaffold/files/*` source, so
   the scaffold then copied empty files over good ones (`.ht.router.php`,
   `modules/README.txt`, …). Composer exits 0 throughout. Cure: delete the
   cached archive and `composer reinstall drupal/core`. Detect it with the
   empty-file check in step 3.
2. **A successful `composer install` can still leave packages missing.** After
   the crashed first pass, a second pass reported "243 installs" and exited 0,
   yet `modules/contrib` held 45 of 156 directories and `themes/contrib` was
   empty. A third pass installed the remaining 157 packages. Only packages
   installed from a dist zip were affected; git-source (`dev-*`) packages and
   the four non-Composer modules survived. Always verify, never trust exit 0.
3. **The inline_entity_form patch never applies by itself.** Reproduced four
   times. `cweagans/composer-patches` first *deletes* the package — the log
   says `Removing package drupal/inline_entity_form so that it can be
   re-installed and re-patched` — then tries `git apply`, which **silently
   skips** the patch because the module lives inside the project's own Git
   working tree and the patch paths (`a/inline_entity_form.module`) do not
   start with the `modules/contrib/inline_entity_form/` prefix Git demands.
   It then falls back to `patch`, and if that binary is absent the whole thing
   ends in `Could not apply patch! Skipping.` — with the package left
   unpatched or, in the worst case, deleted. This is the mechanism behind the
   old "composer uninstalled inline_entity_form" incident.

   Consequences for the server: make sure the `patch` binary is installed
   (`apt-get install patch`; it is missing from many slim images), and always
   run the `grep` check in step 4. Consider setting
   `"composer-exit-on-patch-failure": true` under `extra` in `composer.json`
   so a failed patch aborts the build loudly instead of shipping quietly.

   The patch also carries a one-line-off hunk header (`@@ -284` while the
   context starts at 285). GNU `patch` absorbs it ("Hunk #1 succeeded at 285,
   offset 1 line"); stricter tools may not.
4. **`.htaccess` protection works — leave it alone.** Across four Composer
   runs the file was never touched (`Skip [web-root]/.htaccess: overridden in
   drupal/legacy-project`) and its checksum was identical before and after.
   The `assert.active` string still appears in the file, but only inside the
   comment that explains why the directive was removed.
5. **Line endings.** Cloning on Windows converts tracked files to CRLF, so 34
   files under `modules/custom` differ from the live site by line endings
   only — content is byte-identical. On Linux the checkout keeps LF. Harmless
   here, but do not let it confuse a diff.
6. **Pre-existing condition, unrelated to deployment:** `views_aggregator`
   2.1.1 declares `core_version_requirement: ^10.3 || ^11` while the site runs
   10.2.4, so the status report lists it as an incompatible module. It is one
   of the hand-installed modules. Same files on the live site, so this ships
   as-is unless it is downgraded first.
7. Contrib code differs slightly from the live site (142 files absent, 22
   extra, spread thinly over ~40 modules — mostly CI/test/doc files). That is
   drift accumulated on the live install; a fresh deploy gets exactly what
   `composer.lock` specifies, and the rehearsal proved that combination works.

## Traps found during the real deployment

8. **A zip built by PowerShell loses two thirds of the payload on Linux, in
   silence.** `Compress-Archive` writes Windows path separators and stores no
   Unix permission bits, so `unzip` on the server recreates the directory tree
   without the traversal bit and then cannot descend into the folders it just
   made. It extracted **659 of 3,950 files** and exited 0. Worse, the two
   integrity checks that a careful person would run both pass: `unzip -t`
   reports "No errors detected" (the archive really is intact — it is the
   extraction that fails), and the byte count on disk matches the source
   exactly. The failure only shows up if you count files *after* extracting.

   Cure: build the archive with `tar`, which Windows 10 and 11 ship at
   `C:\Windows\System32\tar.exe`:

   ```powershell
   cd c:\laragon\www\tec\sites\default
   tar -czf c:\laragon\backups\tec-files.tar.gz files private
   ```

   It is also five times faster (5 s versus 54 s) and 6 MB smaller. GNU tar on
   the receiving end prints `Ignoring unknown extended header keyword
   'SCHILY.fflags'` once per file; that is Windows metadata Linux does not use,
   and it is harmless.
9. **Drupal's `.htaccess` does not know about `.md` files.** It blocks `.yml`,
   `.php`, `.inc`, `.install` and friends, so `settings.php`, `config/sync` and
   `composer.json` all correctly returned 403 — but `docs/backlog.md` was
   downloadable by anyone who guessed the URL, and that file documents the
   server layout, the credential incident and the internal roadmap. Anything
   added to the repository in a format Drupal's authors never anticipated is
   public by default. Deny it at the vhost level:

   ```apache
   <DirectoryMatch "^/var/www/erp/(docs|patches)">
       Require all denied
   </DirectoryMatch>
   <FilesMatch "^(README|CHANGELOG|CONTRIBUTING|INSTALL|UPDATE|MAINTAINERS|USAGE|COPYRIGHT)\.(md|txt)$">
       Require all denied
   </FilesMatch>
   ```

   Add the block to **both** vhost files. Certbot copies `erp.conf` into
   `erp-le-ssl.conf` when it installs the certificate, and from then on the two
   drift independently — a rule added only to the HTTP vhost does nothing on
   the HTTPS site that everyone actually uses.

## Post-deploy checklist

```bash
drush status                     # "Drupal bootstrap: Successful"
drush cr
```

- All enabled extensions resolve on disk — the rehearsal had **192** and none
  missing.
- Row counts against the source database: `tec_order_field_data` 123,
  `tec_inventory_field_data` 4,987, `tec_product_field_data` 214,
  `tec_line_item_field_data` 583, `file_managed` 315, `config` 1,255.
- `/user/login` returns 200; `/`, `/o/queue`, `/stock`, `/production/log`
  return 200 when logged in as administrator (307 to the login page when
  anonymous, via r4032login).
- `/o/queue` shows orders with a computed TOTAL row; `/stock` shows materials
  with suppliers and non-zero `Stock after queue` / `Required` columns;
  `/production/log` lists dated entries.
- Image styles generate: request any
  `/sites/default/files/styles/small_40x40/public/...` URL and expect 200.
- `drush core:requirements --severity=1` — expect only the known items
  (Color Field libraries, cron, PWA/HTTPS, the entity-definition mismatch
  inherited from the dump, and `views_aggregator`). Anything new is a
  deployment problem.

### From outside the server

Run these against the public domain, not from a shell on the box — the point
is to see what a stranger sees. Everything except the last two must be denied:

| URL | Expected |
|---|---|
| `/sites/default/settings.php` | 403 |
| `/config/sync/system.site.yml` | 403 |
| `/composer.json` | 403 |
| `/.git/config` | 403 |
| `/docs/backlog.md` | 403 (only after trap 9 is fixed) |
| `/README.md` | 403 |
| `/robots.txt` | 200 |
| `/user/login` | 200, title "Log in \| Acta Total Enterprise Control" |

Also confirm `http://` answers **301** to `https://`, and that the certificate
names the right domain. Expect a scanner to find the login form within minutes
of the DNS record going live — one hit the site 20 minutes after launch. That
is background noise, not a targeted attack, but it is the reason `fail2ban`
matters and the reason no account should keep a weak password.

## Settled during the real deployment

HTTPS via Certbot with the Apache plugin, which writes `erp-le-ssl.conf`,
enables the 301 redirect and installs a renewal timer (verify with
`certbot renew --dry-run`). File ownership and permissions as in step 6.
Opcache confirmed loaded under PHP-FPM. The PWA "HTTPS off" warning disappears
by itself once the certificate is in place.

## Still open

Cron scheduling (Ultimate Cron reported 11 jobs behind), outbound mail from
`erp@anvfightgear.com` with DKIM and DMARC, off-server backups of the
production database, and a way to reach the site while DNS is still
propagating (a `hosts` entry, or a temporary `ServerAlias` on the droplet IP —
note that `trusted_host_patterns` will reject the bare IP by design).
