# Production deployment on cPanel

This guide deploys Worifinance from `https://github.com/shseam13/worifinance` to a cPanel account. Replace hosting placeholders such as `CPANEL_USER`, database names, and domain values with those from your hosting account. Do not paste passwords, private keys, `.env` contents, or reset links into tickets or chat.

## Requirements and cPanel setup

- PHP 8.5 selected for the domain in **MultiPHP Manager**, with `ctype`, `curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `xml`, and `zip` enabled.
- MariaDB 10.11.19 (the reported version of the hosting database).
- Composer 2 and Git available in cPanel Terminal.
- A MariaDB database and database user created with **MySQL Database Wizard** and granted privileges on that database.
- HTTPS enabled for the domain.
- A mailbox on the domain (or another authenticated SMTP account) for password-reset email.

The application does not require a local MySQL server. Development and automated tests use SQLite.

In **Domains**, set the site's document root to the application's `public` directory, for example:

```text
/home/CPANEL_USER/apps/worifinance/public
```

Never set the document root to the Laravel project root. The `.env`, `vendor`, and `storage` directories must not be web-accessible.

### If cPanel locks the primary domain to `public_html`

Ask the host first whether they can change the document root for the primary domain. If they cannot, do **not** move the Laravel project into `public_html` and do not create another nested `public` directory. Keep the application at `/home/worifinance/apps/worifinance`, publish only its web-public files into `/home/worifinance/public_html`, and use a front controller there that boots Laravel from the private application directory.

First inspect the current document root and take a backup. Do not replace an existing website or files you need; ask the host before proceeding if `public_html` contains another live site or valuable files:

```sh
APP_DIR=/home/worifinance/apps/worifinance
DOCROOT=/home/worifinance/public_html
ls -la "$DOCROOT"
BACKUP="$HOME/public_html.backup.$(date +%Y%m%d-%H%M%S)"
cp -a "$DOCROOT" "$BACKUP"
echo "Backup created at $BACKUP"
```

After confirming `public_html` is empty or contains only a replaceable default page, copy the Laravel public assets and rewrite rules. This copies only the contents of `public`; it does not copy `.env`, `vendor`, or the private application code:

```sh
cp -a "$APP_DIR/public/." "$DOCROOT/"
```

Replace `public_html/index.php` with this front controller. It points directly to the existing Laravel project; it does not move or expose the project directory:

```sh
cat > "$DOCROOT/index.php" <<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appDirectory = '/home/worifinance/apps/worifinance';

if (file_exists($maintenance = $appDirectory.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appDirectory.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appDirectory.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
PHP
```

The copied `.htaccess` sends non-file requests to this `index.php`. Make sure it exists as `$DOCROOT/.htaccess`. Link public uploads into the actual web document root (the regular `storage:link` command creates the link under the app's own `public` directory, not necessarily cPanel's `public_html`):

```sh
if [ -L "$DOCROOT/storage" ]; then
    ls -l "$DOCROOT/storage"
elif [ -e "$DOCROOT/storage" ]; then
    echo "STOP: public_html/storage already exists; inspect it before changing anything."
else
    ln -s "$APP_DIR/storage/app/public" "$DOCROOT/storage"
fi
```

Verify `/home/worifinance/public_html/index.php` and `.htaccess` exist, `vendor/autoload.php` exists under the private app directory, and the storage link points to `/home/worifinance/apps/worifinance/storage/app/public`. Test the site and uploaded project images. On future releases, copy the new contents of `$APP_DIR/public/` into `$DOCROOT/` after taking a backup, then restore the Laravel-bootstrapping `index.php` shown above; do not replace it with the app's unmodified `public/index.php`, whose relative paths assume Laravel's own public directory is the document root.

## First deployment

### 1. Clone the repository

For a public GitHub repository:

```sh
mkdir -p "$HOME/apps"
cd "$HOME/apps"
git clone https://github.com/shseam13/worifinance.git worifinance
cd "$HOME/apps/worifinance"
```

For a private repository, first configure read-only GitHub access using cPanel **Git Version Control** or a repository deploy key. Do not put a GitHub password or personal access token in the clone URL or shell history. Then use its SSH clone URL:

```sh
mkdir -p "$HOME/apps"
cd "$HOME/apps"
git clone git@github.com:shseam13/worifinance.git worifinance
cd "$HOME/apps/worifinance"
```

If cPanel requires a different directory, use that same absolute path in all commands below and set the domain document root to its `public` subdirectory.

### 2. Check the PHP and Composer commands

The common cPanel PHP 8.5 CLI executable is `/usr/local/bin/ea-php85`. Confirm the actual path with the hosting provider and use that path for every Artisan command and in the cron entry:

```sh
PHP_BIN=/usr/local/bin/ea-php85
"$PHP_BIN" -v
composer --version
git status --short --branch
```

The PHP version must report 8.5. If `/usr/local/bin/ea-php85` is not present, substitute the PHP 8.5 CLI path supplied by the host.

If `composer` prints `command not found`, Composer is not on this Terminal session's `PATH`. Check common cPanel locations without changing the application:

```sh
command -v composer
ls -l /usr/local/bin/composer /opt/cpanel/composer/bin/composer "$HOME/bin/composer" 2>/dev/null
```

If a Composer executable is listed, use its full path in place of `composer` below, for example `/opt/cpanel/composer/bin/composer install ...`. If none is present, ask the hosting provider to enable Composer 2 for your cPanel account or provide its supported installation instructions. Do not continue with dependency installation skipped: this GitHub repository intentionally does not include `vendor`.

### 3. Create and configure `.env`

```sh
cd "$HOME/apps/worifinance"
cp .env.example .env
nano .env
```

Set the production values in the editor. Keep `APP_KEY` empty for the first deployment; the command in the next step generates it. Enter the exact database name, username, and host shown in cPanel (database and username commonly include a cPanel account prefix):

```dotenv
APP_NAME=Worifinance
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=CPANEL_DATABASE_HOST
DB_PORT=3306
DB_DATABASE=CPANEL_DATABASE_NAME
DB_USERNAME=CPANEL_DATABASE_USER
DB_PASSWORD="CPANEL_DATABASE_PASSWORD"

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=public

MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=CPANEL_SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=YOUR_FULL_MAILBOX_ADDRESS
MAIL_PASSWORD="YOUR_MAILBOX_PASSWORD"
MAIL_FROM_ADDRESS=YOUR_FULL_MAILBOX_ADDRESS
MAIL_FROM_NAME="Worifinance"
```

Use the database and SMTP host/port/encryption values provided by the hosting provider. For implicit TLS, the provider may require `MAIL_SCHEME=smtps` and port `465` instead. Use a mailbox that is authorized to send from `MAIL_FROM_ADDRESS`. Keep `APP_DEBUG=false` and use the HTTPS site URL: reset links are generated from `APP_URL`.

Save and close nano with **Ctrl+O**, **Enter**, then **Ctrl+X**. Do not print `.env` to the terminal or add it to Git.

### 4. Install dependencies, build the application, and migrate

The repository release must include the compiled `public/build` directory and its Vite manifest. Build it on the development machine with `npm ci` and `npm run build` before pushing the release. Node.js is not required on the cPanel server.

Run these commands from the project directory:

```sh
cd "$HOME/apps/worifinance"
PHP_BIN=/usr/local/bin/ea-php85
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
"$PHP_BIN" artisan key:generate
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan storage:link
chmod -R u+rwX,g+rwX storage bootstrap/cache
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
```

Only run `key:generate` for the first install. On later releases, preserve the existing `APP_KEY`; changing it can invalidate encrypted data and sessions. Back up the production database before migrations or any release that changes the schema.

Verify the release:

```sh
"$PHP_BIN" artisan about
"$PHP_BIN" artisan migrate:status
"$PHP_BIN" artisan route:list
"$PHP_BIN" artisan schedule:list
test -f public/build/manifest.json && echo "Vite build manifest is present"
```

If `storage:link` reports that the link already exists, verify that `public/storage` points to this application's `storage/app/public`; do not remove an existing path until you have confirmed what it contains. If the host blocks symlinks, configure its supported public-storage mapping.

### 5. Create the first Super Admin

Run the interactive command and enter the administrator's details only at the prompts:

```sh
"$PHP_BIN" artisan app:create-super-admin
```

Choose a unique email and a password of at least 12 characters containing uppercase and lowercase letters, a number, and a symbol. Never create an administrator through public investor registration.

### 6. Configure scheduler cron

In cPanel **Cron Jobs**, choose **Once Per Minute** in **Common Settings** (or enter `*` for minute, hour, day, month, and weekday). When cPanel presents separate schedule fields and a **Command** field, put only the command below in **Command**—do not paste the five-field `* * * * *` schedule into the command box. Replace `CPANEL_USER` and the project path. Confirm the PHP binary path with the host first.

Command field:

```sh
cd /home/CPANEL_USER/apps/worifinance && /usr/local/bin/ea-php85 artisan schedule:run >> /dev/null 2>&1
```

If your cron editor accepts a single crontab line instead of separate schedule fields, use this complete line instead:

```cron
* * * * * cd /home/CPANEL_USER/apps/worifinance && /usr/local/bin/ea-php85 artisan schedule:run >> /dev/null 2>&1
```

The scheduler processes annual account charges daily, including retries for unpaid charges. Without this cron job, scheduled annual charges will not run.

### 7. Verify the live site and email

1. Open `https://YOUR_DOMAIN` and confirm HTTPS, the home page, project catalogue, English/Bangla switch, and static assets load.
2. Sign in at `/admin/login`; set platform payment instructions, the BDT/USDT display rate, annual fee, minimum withdrawal, and referral commission as appropriate.
3. Use **Update password** in the Super Admin panel to set a unique production password.
4. Sign out and test **Forgot password?** with an account you control. Confirm the reset email arrives, the link uses HTTPS and the correct domain, and the new password works. Do not use real investor accounts for the test.
5. If the email does not arrive, inspect Laravel logs in `storage/logs`, verify the SMTP host/port/TLS/mailbox settings with the provider, then clear cached configuration and rebuild it:

   ```sh
   "$PHP_BIN" artisan optimize:clear
   "$PHP_BIN" artisan config:cache
   ```

6. Upload and verify the Binance/USDT QR image in Super Admin settings. Confirm the exact receiving wallet and network independently before accepting deposits.
7. Confirm the cron job runs and `"$PHP_BIN" artisan schedule:list` shows the annual-charge task.

## Updating an existing deployment

Before each release, make a database backup in cPanel **phpMyAdmin → Export** (or use the host's approved `mysqldump` command), and verify that the release's `public/build` directory was built locally and pushed to GitHub.

Then run in cPanel Terminal to update from the deployed `main` branch:

```sh
cd "$HOME/apps/worifinance"
PHP_BIN=/usr/local/bin/ea-php85
git status --short --branch
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan about
"$PHP_BIN" artisan migrate:status
```

Do not overwrite `.env`, regenerate `APP_KEY`, or run development migrations/seeds against production. If the deployment cannot update `public/build` through Git, transfer the locally generated build directory as part of the release before running `view:cache`.

## Operational safety

- Keep `APP_DEBUG=false`; restrict write access to `.env`, and never commit or print it.
- Only `storage` and `bootstrap/cache` need to be writable by the PHP process. Do not use `chmod 777`.
- Keep database backups secure and test restore procedures before relying on them.
- Password reset uses Laravel's `password_reset_tokens` table (created by the initial schema migration) and SMTP email. Do not use the `log` mailer in production.
- Investor/admin password reset requests return the same message whether or not an account exists. Reset tokens expire according to Laravel's `auth.passwords.users` configuration and can only be used once.
- Super Admin password changes require the current password, record an audit event without recording the password, rotate the remember token, and delete other sessions when the configured session driver is `database`.
- Public project imagery must be verified and authorized for use. Sample/demo profiles are illustrative and must not be represented as verified properties.
- Deposit requests are not connected to a bank, payment gateway, or automatic blockchain verification. A Super Admin must independently verify received funds before approval.
- Investment requests reserve wallet funds and project units until reviewed. Profit distributions require explicit review/approval/posting. Referral commissions require explicit Super Admin credit.
- Withdrawal payouts are external: verify and send the payout before marking a request paid. No payout is sent automatically.
- The annual account charge uses available BDT funds only; insufficient balances remain due for retry and do not suspend accounts.
- Verify the receiving account, wallet address, QR image, network, and payment instructions before publishing. Never place passwords, private keys, or other secrets in public payment instructions.

For system behavior and financial workflow details, see [README.md](./README.md).
