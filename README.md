# Stock Card & Inventory Management System

A Laravel web application that digitizes the paper **Stock Card** and **Requisition and Issue Slip (RIS)** workflow for office supplies and consumables.

- Every receipt, issuance, adjustment and reversal is posted to a single append-only ledger (`stock_transactions`). Each Stock Card row is one ledger row with a running balance.
- An RIS goes through Draft → Submitted → Pending Approval → Approved → Partially / Fully Issued → Received / Completed. It can also be Returned, Rejected or Cancelled.
- Stock is deducted **only** when an issuance is posted, and every issuance writes a Stock Card row referenced by the RIS number.
- Access is role-based and enforced on the server. There are two roles: **Administrator / Inventory Officer** (the supply office, which does all inventory processing, approval and issuance) and **Requesting Personnel** (staff from other departments, who file and track their own requisitions).
- Reports can be printed (A4), exported to PDF or downloaded as CSV for Excel. Sensitive actions are recorded in a full audit log.

**Stack:** Laravel 13 · PHP 8.3+ · MySQL 8+ (SQLite for tests) · Blade + Bootstrap 5.3 · Bootstrap Icons · Chart.js · barryvdh/laravel-dompdf.
Bootstrap, Bootstrap Icons, Chart.js and the Inter font load from public CDNs (jsdelivr / Google Fonts), so there is no Node build step. The browser needs internet access for styling.

---

## Running in production with Docker (this PC, office network)

The app, a MySQL database and the scheduler run as three containers defined in `compose.yaml`. You need Docker Desktop.

| Task | Command |
|---|---|
| Start, or update after a code change | `docker compose up -d --build` |
| Stop (all data is kept) | `docker compose down` |
| Status | `docker compose ps` |
| Application logs | `docker compose logs -f app` |
| Run an artisan command | `docker compose exec app php artisan <command>` |
| Create an administrator | `docker compose exec app php artisan app:create-admin` |

- **Address:** the system is served at `http://<this PC's IP>:8000`, the `APP_URL` in `.env`.
- **Database:** stored in the Docker volume `stock-card_mysql-data`. Its passwords are the generated `DOCKER_DB_*` values at the end of `.env`.
- **Files:** receipt attachments and e-signatures stay on this PC in `storage/app` (mounted into the containers).
- **Settings changes:** after editing `.env`, run `docker compose up -d` to apply them.
- **Automatic start:** containers restart on their own when Docker Desktop starts. Enable *Start Docker Desktop when you sign in* in Docker Desktop's settings so the system is available after a reboot.
- **Importing older data:** data from the earlier SQLite file was imported once with `php artisan app:import-sqlite`.
- **Backups:** `docker compose exec mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" stock_card' > backup.sql`

## 1. Install dependencies

Requirements: PHP 8.3+ with the `pdo_mysql`, `mbstring`, `gd`, `dom` and `fileinfo` extensions, Composer 2, and MySQL 8+ (or MariaDB 10.6+).

```bash
composer install
```

## 2. Configure environment variables

```bash
cp .env.example .env          # Windows PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

Edit `.env`:

| Variable | Purpose |
|---|---|
| `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection |
| `APP_TIMEZONE` | Display and storage timezone (default `Asia/Manila`) |
| `APP_URL` | Base URL, e.g. `http://localhost:8000` |
| `SESSION_SECURE_COOKIE` | Set to `true` when served over HTTPS |
| `DEMO_USER_PASSWORD` | *Development only.* Password for the seeded demo accounts. Leave it empty to have a random one generated and printed. |

## 3. Create the database

```sql
CREATE DATABASE stock_card CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'stock_card'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON stock_card.* TO 'stock_card'@'localhost';
FLUSH PRIVILEGES;
```

(Quick local trial without MySQL: set `DB_CONNECTION=sqlite` and remove the other `DB_*` lines. Laravel uses `database/database.sqlite`.)

## 4. Run migrations and seeders

```bash
php artisan migrate --seed
```

- `ReferenceDataSeeder` (every environment) creates the roles and default settings. The defaults are Entity: *Bureau of Internal Revenue* and Division: *Large Taxpayers Division – Cebu*; both can be changed under **Settings**.
- `DemoDataSeeder` (skipped when `APP_ENV=production`) creates **clearly labelled demonstration data**: offices, demo users, items (Air Freshener – Can, A4 Bond Paper – Ream, Canon 337 Toner – Cartridge, Binder Clips – Box, and others), receipts, an adjustment, completed RIS documents, a partially issued multi-item RIS, and RIS documents waiting at each workflow stage. All stock movements go through the same services as real postings. The Air Freshener Stock Card reproduces the paper example: 28 → 24 → 19 → 17 → +15 = 32.

To rebuild from scratch: `php artisan migrate:fresh --seed`.

To check ledger integrity at any time: `php artisan inventory:verify`. It replays every item's transactions and confirms each running balance and cached balance.

## 5. Start the local development server

```bash
php artisan serve
```

Open <http://localhost:8000>. (If you use Laravel Herd, you can also serve the folder as a Herd site.)

## 6. Log in with a development account

| Email | Role |
|---|---|
| `admin@demo.local`, `officer@demo.local` | Administrator / Inventory Officer: everything (items, stock cards, receipts, adjustments, RIS approval and issuance, reports, users, settings, audit logs) |
| `requester@demo.local`, `staff@demo.local` | Requesting Personnel (other departments): file and track their own RIS, acknowledge receipt |

All demo accounts share one password: the value of `DEMO_USER_PASSWORD`, or the random password the seeder printed.

**Production:** never seed demo data. Create the first administrator interactively. The password is prompted for and never stored in code:

```bash
php artisan app:create-admin --name="Juan Dela Cruz" --email=admin@yourdomain.gov.ph
```

## 7. Create an item and its opening balance

*Inventory Items → Add Item* (administrators). Enter the stock number (must be unique), name, description, category, unit, office/location and re-order point, plus the **initial stock quantity** and its as-of date. This posts the item's first Stock Card row (the opening balance).
Item balances can never be typed in. They change only through receipts, issuances, adjustments and reversals.

## 8. Receive stock

*Stock Receipts → Receive Stock*. Fill in the date, supplier/source, reference document (DR / PO / IAR / PTR / Other) and number, and the receiving personnel. You can attach an optional PDF or image (stored privately and downloadable only by authorized users). Add one or more item lines and post. Each line adds a Stock Card row and increases the balance. Submitting the same form twice posts it only once.

## 9. Submit, approve and issue an RIS

1. **Requester:** *Requisitions & Issue Slips → New RIS*. Choose the office, add items and quantities (live "Stock Available?" hints are shown), enter the purpose, then **Save & submit**.
2. **Administrator:** open the RIS and choose **Forward for approval**. This records Stock Available (Yes/No) from current balances, and you can add remarks or return the request for correction.
3. **Administrator:** enter approved quantities, then **Approve**, **Return for correction** or **Reject** (remarks required). Approval does **not** change stock. Requesters can never approve.
4. **Administrator:** **Issue stock**. Enter the quantity to issue now for each line (it cannot exceed the approved remainder or the stock on hand). Partial issuance is supported, and the status becomes Partially or Fully Issued. Each posted line adds a Stock Card row with the RIS number as its reference.
5. **Requester:** **Acknowledge receipt**, which marks the RIS as Received / Completed.

The sidebar badge shows how many RIS are waiting on your role. The *Awaiting my action* tab lists them.

## 10. View and print a Stock Card

*Stock Cards → Open* an item. The page shows the paper-form header (Office, Location, Item, Description, Unit, Stock No., Re-order Point) and the transaction table (Date, Reference, From Whom Received / To Whom Issued, Received Qty., Issued Qty., Balance Qty.). You can filter by date range, transaction type or reference. **Print** opens an A4 paper-style layout; **PDF** downloads it. If you filter from a start date, the balance brought forward is shown.

Mistakes are corrected with **Reverse** (administrators, reason required). This posts an opposite entry, and the original row stays visible with a *Reversed* marker. Other corrections, such as damage, loss or physical count differences, go through **Stock Adjustments**, which require a reason, a reference and an explanation.

## Email notifications

| Event | Who is emailed |
|---|---|
| A requester submits or resubmits an RIS | All active administrators (except the person who submitted it) |
| An RIS is approved, returned, rejected, cancelled, or partially or fully issued | The requester, with remarks and the quantities issued and remaining |
| A posting takes an item to its re-order point, or to zero | All active administrators. You get one alert per crossing; it repeats only after the item is restocked above the re-order point. |
| Weekdays at `LOW_STOCK_DIGEST_TIME` (default 08:00) | All active administrators: a table of every item at or below its re-order point |

Turn each type on or off, and add extra recipients such as a shared office mailbox, under **Settings → Email notifications**. Emails are sent only after the posting is saved. If the mail server fails, the error is logged and the posting still succeeds.

To deliver real email, set SMTP in `.env`. With the default `MAIL_MAILER=log`, emails go to `storage/logs/laravel.log`. For Gmail:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=youraddress@gmail.com
MAIL_PASSWORD=your-16-character-app-password   # Google Account → Security → App passwords
MAIL_FROM_ADDRESS=youraddress@gmail.com
```

Then run `php artisan config:clear`. Recipients are the users' account emails, so give each user a real address under **User Management**.

- With `QUEUE_CONNECTION=sync`, emails are sent immediately. With `database`, keep `php artisan queue:work` running.
- The daily summary needs the scheduler. Run `php artisan schedule:work` during development, or schedule `php artisan schedule:run` every minute in Windows Task Scheduler or cron. You can send it on demand with `php artisan inventory:low-stock-digest`.

## 11. Run the test suite

```bash
php artisan test
```

The tests use an in-memory SQLite database (see `phpunit.xml`). They cover:

- item creation with a unique stock number, and the opening balance
- receipts increase the balance; issuances decrease it; running balances (the 28 → 32 example)
- issuance over available stock is rejected; quantity and date rules
- duplicate receipt and issuance submissions post once
- partial issuance tracks the remaining quantity and status
- approval alone does not deduct stock
- issuance creates a Stock Card row referenced by the RIS number
- reversal restores the balance while keeping the original row; a second reversal is refused
- posted ledger rows cannot be updated or deleted
- role restrictions (approve, issue, adjust, receive, edit, audit) and requester data isolation
- a stale concurrent issuance cannot drive the balance negative
- search, filters and pagination
- every page and report renders; CSV, print and PDF exports contain the correct data
- reorder alerts follow the configured reorder point

---

## Architecture notes

| Area | Where |
|---|---|
| Ledger posting, balance computation, receipts, adjustments, reversals | `app/Services/InventoryService.php` |
| RIS workflow state machine and issuance | `app/Services/RequisitionService.php` |
| Report definitions (shared by screen, print, PDF and CSV) | `app/Services/ReportService.php` |
| Role → ability map | `config/permissions.php` (registered as Gates in `AppServiceProvider`) |
| Record-level RIS rules | `app/Policies/RequisitionPolicy.php` |
| Audit logging | `app/Services/AuditLogger.php`, table `audit_logs` |
| Document numbers (RIS-YYYY-####, RCV-, ISS-, ADJ-) | `app/Services/DocumentNumberService.php` (row-locked counters) |
| Schema | `database/migrations/2026_01_01_*` |

**Integrity rules**

- Every posting runs in a DB transaction and takes `SELECT … FOR UPDATE` on the item row, then recomputes the balance from the ledger before writing. Two simultaneous issuances for the same item are serialized, and the second one sees the reduced balance. Issuances also lock the RIS row, so the same approved quantity cannot be issued twice.
- `quantity_received` and `quantity_issued` are unsigned integers. A row never has both, the balance never goes negative, and a reference is required.
- A transaction cannot be dated in the future or earlier than the item's latest posting. This keeps posting order chronological, so every `balance_after` snapshot stays valid.
- Ledger rows are immutable at the model level. Corrections are new reversal or adjustment rows. `reverses_transaction_id` is unique, so a row can be reversed only once.
- Receipt, issuance and adjustment forms carry a one-time `idempotency_key` (unique in the DB) that blocks double posting.
- `inventory_items.current_balance` is a cache written only by `InventoryService`. It is not mass-assignable and is checked against the ledger on every posting.

**Security:** bcrypt password hashing, CSRF on all forms, login rate limiting (5/min per email and IP), posting rate limits, server-side validation everywhere, Blade output escaping, Eloquent parameter binding, private storage for attachments (PDF/JPG/PNG, max 5 MB, served only through an authorized route), encrypted sessions, CSV formula-injection neutralization, and deactivated accounts are signed out on their next request.

**Signatures:** the RIS records *authenticated system actions* (user, designation, timestamp) for Requested / Approved / Issued / Received by. These are not cryptographic digital signatures, and the printed form says so. Wet signatures can be added to the printout.

**Quantities** are whole units (Can, Ream, Box, Cartridge…). If you need fractional units, change the quantity columns to `decimal(12,2)` and the related validation rules.
