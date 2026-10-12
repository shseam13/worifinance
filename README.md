# Worifinance

Worifinance is a Laravel 13 application for presenting fractional property projects in English and Bangla. Its responsive public experience uses the supplied Worifinance brand, a translucent header, a mobile bottom navigation, and project-detail pages.

The database foundations are designed for the cPanel host's MariaDB `10.11.19`. Local development defaults to SQLite; no local MySQL/MariaDB server is required.

## Requirements

- PHP 8.5 (the project requires PHP `^8.3`)
- Composer 2
- PHP extensions: `ctype`, `curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_sqlite` for local SQLite, `pdo_mysql` for MariaDB, `tokenizer`, `xml`, and `zip`
- Node.js and npm to compile frontend assets locally

## Local setup (Windows PowerShell)

```powershell
cd D:\worifinance
composer install
npm install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan test
php artisan serve
```

Open <http://127.0.0.1:8000>. The default database seeder does not create fictional property listings. No real property data has been supplied, so the public project catalogue remains empty until verified details are added by a Super Admin.

To populate a prototype catalogue, sign in as Super Admin, open **Project management**, and choose **Enable demo projects**. This restores/publishes the three illustrative profiles and their bundled SVG artwork; every one is visibly marked as sample content and can be hidden again with **Hide demo projects**. Do not treat their descriptions or example pricing as factual.

To use MariaDB locally or on cPanel, set the `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values in `.env`, and ensure the PHP runtime has `pdo_mysql` enabled. On cPanel use the database name, username, and host shown by that hosting account.

## Data model

- Users retain a referral relationship and a role/status. Public account creation must never grant the Super Admin role.
- Projects have ordered image and document records. Versioned project terms preserve the unit price, effective dates, and return-rate settings used when an investment is confirmed.
- Wallets are held separately by currency; immutable ledger entries record posted credits, debits, and reserved funds. Displayed wallet balances must be kept consistent with ledger entries within a database transaction.
- Deposit requests remain pending until a Super Admin verifies the transfer and approves it; the instructions/version and Binance payment QR shown to the investor are retained with each request. A Super Admin can upload a public USDT QR image in platform settings.
- Investments retain unit counts, the exact project-term version, payment amount, currency, and the exchange-rate snapshot used for conversion.
- Profit distributions and their per-investment allocations support pro-rata calculation and explicit admin approval before credit.
- Referral commissions, yearly membership charges, versioned platform settings, exchange rates, and admin audit records have separate tables. Super Admin can set the site-wide display currency (BDT or USDT) and the BDT-per-USDT rate; conversions affect presentation only and never alter stored balances or historical transaction snapshots.

All monetary amounts use decimal columns. BDT and USDT amounts are stored independently, and exchange-rate snapshots are retained on converted transactions. Do not calculate historical entries using a subsequently changed rate.

## Authentication and account access

Investor registration and login, a separate Super Admin login portal, role-protected account dashboards, session logout, and login throttling are implemented. Investor and Super Admin login pages link to a rate-limited password-reset flow using expiring, single-use Laravel reset tokens sent by email; reset requests do not reveal whether an email is registered. Configure production SMTP before relying on reset emails. Super Admins can change their password from **Update password** in the admin navigation after verifying the current password; the action is audited without storing the password. Public registration can only create investor accounts. Create the initial administrator after migrations using the interactive command below; it prompts for credentials and never stores a default password in source code:

```powershell
php artisan app:create-super-admin
```

The investor dashboard shows the signed-in user's BDT/USDT available and reserved balances, confirmed and pending investment records, credited profit and referral totals, and their own recent deposit/withdrawal requests. Investors can submit BDT or USDT deposit references only for currencies with configured payment instructions; the current USDT QR is displayed where configured. Requests remain pending and do not credit a wallet until a Super Admin verifies the transfer and approves it. Investment requests reserve the wallet amount and project units while awaiting Super Admin review. Approval debits the reserved wallet funds; rejection releases the wallet reservation and project units. A confirmed investment by a referred investor creates one pending BDT commission using the current configured rate and confirmed BDT principal; the commission rate and basis are snapshotted, and a Super Admin must explicitly credit it. Payment, investment, and commission decisions create ledger entries and audit records.

Super Admins can create profit-distribution drafts for completed project periods. Allocations use each confirmed investment's saved project-term annual rate, prorated by eligible calendar days over 365. Overlapping periods for the same project are blocked. Drafts must be approved and explicitly posted; posting credits each investor wallet and writes ledger entries atomically. USDT distributions retain the exchange-rate snapshot used to calculate allocations. The investor dashboard displays only posted distributions.

The Super Admin dashboard reports platform investor/project totals, confirmed investment volume, approved payable and posted profits by currency, payable/credited referral commissions, wallet totals, review queues, recent requests and investments, and project investment summaries. Admins can create/edit/archive projects, publish bilingual profiles with uploaded cover/gallery images, and revise dated project-term versions. Referral reports show account attribution and confirmed investment totals. Platform settings version and audit annual account fees, minimum withdrawal amounts, referral commission rates, site-wide display currency, display-conversion rates, public BDT/USDT payment instructions, and the Binance QR image. The USDT-to-BDT rate is administrator-maintained and should not be treated as a live market quote. Annual BDT charges are created on the account anniversary and automatically debit only unreserved BDT wallet funds; insufficient funds leave a versioned charge due for daily retry without suspending the account. Due and collected charges appear in investor and Super Admin history. Super Admin uses a separate responsive operations-panel layout with a grouped left sidebar rather than the investor/public navigation. Both dashboards are available in English and Bangla.

Super Admin **Investor accounts** provides searchable and status-filtered investor records, profile/contact editing, account suspension/reactivation, wallet available/reserved balances, recent investment/deposit/withdrawal activity, and referral relationships. Profile and access changes are written to the admin audit log. Account management does not delete investors or directly modify wallet balances.

Project management includes uploaded images displayed in a thumbnail gallery and keyboard-operable full-screen viewer. Deposit requests support manual review and wallet credits after verification; they are not connected to a payment provider or automatic blockchain/bank reconciliation. Referral commissions are calculated on confirmed investments and can be credited to the referrer's BDT wallet by a Super Admin. Investors can request BDT or USDT withdrawals above the configured BDT minimum; funds are reserved while pending, and USDT requests retain the current exchange-rate snapshot. A Super Admin must pay externally before marking a request paid and recording the payout reference, or reject it with a note to release the reservation. No payout is sent or verified automatically. The annual membership-charge scheduler must be enabled on production using Laravel's scheduler cron entry. Do not treat payment references as proof of receipt; Super Admin must independently verify payment in the receiving account before approving a deposit. Investments and profit distributions are admin-reviewed platform operations, not promises of guaranteed returns.

Project images use Laravel's `public` filesystem disk. On local or cPanel deployments, run `php artisan storage:link` (or create the equivalent `public/storage` symlink) and ensure `storage/app/public` is writable by PHP. Apply migrations with `php artisan migrate --force` during deployment. Existing illustrative sample projects are retained as drafts by a forward migration; they are not deleted because they may be referenced by existing records.

## cPanel notes

See [DEPLOYMENT.md](./DEPLOYMENT.md) for document-root, database, Composer, migration, and static-asset setup.
