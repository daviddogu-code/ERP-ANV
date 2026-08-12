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

---

# Deployment procedure

Validated on 2026-08-12 by rebuilding the whole ERP from scratch in a
throwaway directory (`tec-ensayo`) using nothing but the three ingredients
below. The rebuilt site served `/`, `/user/login`, `/o/queue`, `/stock` and
`/production/log` with real data and zero PHP errors, so the ingredients are
sufficient — but only if the verification steps here are followed, because
`composer install` alone does **not** produce a correct tree (see "Traps").

## Ingredients

| Ingredient | Contains | Notes |
|---|---|---|
| `github.com/daviddogu-code/ERP-ANV`, branch `main` | everything except `/core`, `/vendor`, and the two runtime file directories | ~17,500 files, 43 MB |
| database dump | the `actatec` schema | 460 tables |
| project archive | `sites/default/files` **and** `sites/default/private` | 2,640 + 1,384 files |

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
   ls modules/contrib | wc -l   # expect 156
   ls themes/contrib  | wc -l   # expect 3
   # no truncated files
   find core modules themes vendor libraries -type f -empty | wc -l
   ```

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
6. Restore `sites/default/files` and `sites/default/private` from the archive.
7. Install `sites/default/settings.php` and edit exactly four things:
   - `$databases['default']['default']['database']` (and user/password/host).
   - `$settings['trusted_host_patterns']` — add the production domain.
     Without it Drupal answers **400 Bad Request** to every request.
   - `$settings['rebuild_access']` — set to **FALSE**. It is `TRUE` in the
     current file, which lets anyone run `rebuild.php`.
   - `$settings['hash_salt']` — keep the existing value; changing it
     invalidates all sessions and one-time login links.

   Leave `config_sync_directory` (`config/sync`) and `file_private_path`
   (`sites/default/private`) alone: both are relative and already correct.
8. Point the web server at the project root. This is a
   `drupal/legacy-project` layout: the document root **is** the repository
   root, not a `web/` subdirectory.
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
  with suppliers and non-zero `Projected` / `Required` columns;
  `/production/log` lists dated entries.
- Image styles generate: request any
  `/sites/default/files/styles/small_40x40/public/...` URL and expect 200.
- `drush core:requirements --severity=1` — expect only the known items
  (Color Field libraries, cron, PWA/HTTPS, the entity-definition mismatch
  inherited from the dump, and `views_aggregator`). Anything new is a
  deployment problem.

## Not covered by this rehearsal

Everything was validated on Windows/Laragon against PHP 8.3.30, MySQL 8.4.3
and Drupal 10.2.4, over HTTP with Drupal's own router. The following still
have to be settled on the server: HTTPS and the PWA "HTTPS off" error, cron
scheduling (Ultimate Cron reported 11 jobs behind), outbound mail, file
ownership and permissions for `sites/default/files` and `.../private`, and
`opcache`/`realpath_cache` sizing under mod_php or PHP-FPM.
