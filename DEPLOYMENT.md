# Worifinance cPanel Deployment

This guide deploys `https://github.com/shseam13/worifinance` to cPanel without requiring Composer on the server. Composer is used on the Windows development computer to build a production `vendor` bundle, which is uploaded to the private application directory.

Replace example account names, paths, database details, and domain names with the values from your cPanel account. Never share or commit `.env`, passwords, API tokens, private keys, or database backups.

## Deployment overview

1. Confirm the host has PHP 8.5 configured correctly for web requests and create the database.
2. Build the production dependencies and frontend assets on the development computer.
3. Clone or update the application code on cPanel; upload and extract the dependency bundle outside `public_html`.
4. Configure `.env` and the domain's public document root.
5. Run Laravel setup, migrations, and create the first Super Admin.
6. Configure the scheduler and verify the live site.

The application and its `.env`, `vendor`, and `storage` directories must stay outside the public document root. Only Laravel's public files should be web-accessible.

## 1. Prepare the hosting account

Ask the host to confirm the following before deploying:

- PHP 8.5 is enabled for the domain's web handler and CLI. The CLI PHP binary may be `/usr/local/bin/ea-php85` or another path supplied by the host.
- Required PHP extensions are enabled: `ctype`, `curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `xml`, and `zip`.
- A MariaDB database and database user have been created, with privileges granted to that database. The reported hosting version is MariaDB 10.11.19.
- HTTPS is enabled for the domain, and an SMTP mailbox is available for password-reset email.
- Git is available in cPanel Terminal if you plan to clone and update the repository there.

The application does not require a local MySQL server; development and automated tests use SQLite. Composer 2 is required on the development computer, not on the cPanel server.

### Important: resolve PHP handler errors first

If the browser displays **“This PHP CGI binary was compiled with force-cgi-redirect enabled”**, PHP is not being invoked through a working web handler, so Laravel has not run yet. Ask the hosting provider to configure the domain's PHP 8.5 Apache/CloudLinux handler. Do not try to fix this by editing cPanel-generated PHP handler lines in `.htaccess`, and do not copy another domain's handler configuration.

The PHP version selected for the web domain in **MultiPHP Manager** is separate from the PHP executable used in Terminal and cron. Do not edit cPanel-generated PHP handler or INI sections manually.

## 2. Build a release on Windows

Run these commands in PowerShell on the development computer. They install only production PHP dependencies, verify the autoloader, create `vendor-production.zip`, and validate the archive entry that must be present:

```powershell
Set-Location 'D:\worifinance'
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Composer dependency installation failed.' }
if (-not (Test-Path '.\vendor\autoload.php')) { throw 'vendor\autoload.php was not created.' }

tar -a -c -f vendor-production.zip vendor
if ($LASTEXITCODE -ne 0) { throw 'Could not create vendor-production.zip.' }

$archiveEntries = tar -tf vendor-production.zip
if ($LASTEXITCODE -ne 0) { throw 'Could not read vendor-production.zip.' }
if ($archiveEntries -notcontains 'vendor/autoload.php') {
    throw 'The archive does not contain vendor/autoload.php.'
}
Get-Item .\vendor-production.zip | Select-Object FullName,Length,LastWriteTime
Write-Output 'Verified archive entry: vendor/autoload.php'
```

Build the frontend assets and commit/push the release code as well:

```powershell
npm ci
if ($LASTEXITCODE -ne 0) { throw 'npm ci failed.' }
npm run build
if ($LASTEXITCODE -ne 0) { throw 'Frontend build failed.' }
```

The code, `composer.lock`, `vendor-production.zip`, and `public/build` must all come from the same release. `vendor` and `vendor-production.zip` are excluded from Git; do not commit them. Repeat the dependency build and upload whenever `composer.lock` changes.

## 3. Put the application code and dependencies on cPanel

Use a private application directory outside `public_html`. The commands below use `/home/worifinance/apps/worifinance`; substitute your actual cPanel username and application path.

### First deployment: clone the repository

For a public GitHub repository:

```sh
mkdir -p "$HOME/apps"
cd "$HOME/apps"
git clone https://github.com/shseam13/worifinance.git worifinance
cd "$HOME/apps/worifinance"
```

For a private repository, configure read-only GitHub access through cPanel Git Version Control or a deploy key, then use the SSH clone URL. Do not put a password or personal access token in the clone URL.

### Upload and extract the dependencies

Use cPanel File Manager or SFTP to upload `vendor-production.zip` into the private application directory, for example `/home/worifinance/apps/worifinance`. Extract it there, not in `public_html`. Confirm the result is:

```text
/home/worifinance/apps/worifinance/vendor/autoload.php
```

If a partial `vendor` directory already exists, rename it in File Manager before extracting. Keep the renamed directory until the deployment has been verified.

Before running Artisan, verify from cPanel Terminal:

```sh
cd "$HOME/apps/worifinance"
test -f vendor/autoload.php \
  && echo "Production dependencies are present" \
  || echo "STOP: upload and extract vendor-production.zip first"
```

Never put `vendor-production.zip` or the `vendor` directory under `public_html`.

## 4. Configure `.env`

Create the environment file once, then edit it in cPanel File Manager or a terminal editor:

```sh
cd "$HOME/apps/worifinance"
cp .env.example .env
nano .env
```

Set the production values. Use the exact database and SMTP settings supplied by the host:

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

For implicit TLS, the host may require `MAIL_SCHEME=smtps` and port `465`. Use an SMTP mailbox authorized to send from `MAIL_FROM_ADDRESS`. Keep `APP_DEBUG=false`; use the HTTPS site URL so password-reset links use the correct domain.

Leave `APP_KEY` empty only for the first installation; Laravel will generate it in step 6. Never regenerate it for an existing installation. Do not print `.env` contents or commit the file.

## 5. Configure the website's public document root

### Preferred: point the domain at Laravel's `public` directory

If cPanel allows changing the document root, set it to:

```text
/home/CPANEL_USER/apps/worifinance/public
```

Never use the Laravel project root as the document root.

### If the primary domain is locked to `public_html`

Keep Laravel in the private application directory and publish only the contents of its `public` directory into `/home/CPANEL_USER/public_html`. Do not move the whole Laravel project into `public_html`.

Before changing files, inspect and back up the current document root. Stop if it contains a live site or files that must be kept:

```sh
DOCROOT=/home/CPANEL_USER/public_html
APP_DIR=/home/CPANEL_USER/apps/worifinance
ls -la "$DOCROOT"
BACKUP="$HOME/public_html.backup.$(date +%Y%m%d-%H%M%S)"
cp -a "$DOCROOT" "$BACKUP"
echo "Backup created at $BACKUP"
```

Copy the Laravel public assets but **exclude `.htaccess`, `index.php`, and `storage`**. This preserves both cPanel's generated PHP handler/INI settings and the custom `index.php` that boots Laravel from outside the web root. The `build/` and `images/` directories are required: Laravel's HTML references CSS, JavaScript, the logo, and project images from these paths. If `rsync` is unavailable, copy `build/`, `images/`, `favicon.ico`, and `robots.txt` in File Manager; do not overwrite `.htaccess`, `index.php`, or an existing `storage` path.

```sh
rsync -a \
  --exclude='/.htaccess' \
  --exclude='/index.php' \
  --exclude='/storage' \
  "$APP_DIR/public/" "$DOCROOT/"
```

Add Laravel's rewrite block to the existing `.htaccess`, leaving all cPanel-generated sections intact:

```apache
# BEGIN Worifinance Laravel rewrites
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
# END Worifinance Laravel rewrites
```

Create `/home/CPANEL_USER/public_html/index.php` as the front controller. Update `$appDirectory` to the real private application path:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appDirectory = '/home/CPANEL_USER/apps/worifinance';

if (file_exists($maintenance = $appDirectory.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appDirectory.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appDirectory.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

The `public_html` front controller depends on `vendor/autoload.php` being installed in the private application directory. `artisan storage:link` creates a link under `$APP_DIR/public/storage`; when the web document root is `public_html`, create a separate link there so uploaded project images and payment QR images are reachable. Link only Laravel's public upload directory, never the whole private `storage` directory:

```sh
if [ -L "$DOCROOT/storage" ]; then
    ls -ld "$DOCROOT/storage"
elif [ -e "$DOCROOT/storage" ]; then
    echo "STOP: $DOCROOT/storage already exists and is not a symlink; inspect it before changing anything."
else
    ln -s "$APP_DIR/storage/app/public" "$DOCROOT/storage"
    ls -ld "$DOCROOT/storage"
fi
```

The link target should resolve to `$APP_DIR/storage/app/public`. Do not replace or remove an existing `storage` path until you have inspected it.

Verify that the copied assets exist in the actual document root before testing the home page:

```sh
test -f "$DOCROOT/build/manifest.json" \
  && echo "Vite manifest is present in public_html" \
  || echo "STOP: copy Laravel public/build into public_html"
test -f "$DOCROOT/images/worifinance.jpg" \
  && echo "Logo is present" \
  || echo "STOP: copy Laravel public/images into public_html"
```

If the Vite filename changes in a later release, use the CSS and JavaScript filenames in `$APP_DIR/public/build/manifest.json` to confirm those files also exist under `$DOCROOT/build/assets`. After future releases, copy updated public assets with the same exclusions, preserving the custom front controller and cPanel `.htaccess`.

## 6. Run Laravel setup

Run commands only after `vendor/autoload.php` exists and the PHP 8.5 CLI path is confirmed. The common path is `/usr/local/bin/ea-php85`; the host may provide another one.

```sh
cd "$HOME/apps/worifinance"
PHP_BIN=/usr/local/bin/ea-php85
"$PHP_BIN" -v
test -f vendor/autoload.php || { echo "Missing vendor/autoload.php; stop."; exit 1; }
```

For the **first installation only**:

```sh
"$PHP_BIN" artisan key:generate
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan storage:link
chmod -R u+rwX,g+rwX storage bootstrap/cache
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
```

Back up the production database before migrations. If `storage:link` says the link already exists, verify its target; do not remove an existing path without checking it.

Verify Laravel and the production build:

```sh
"$PHP_BIN" artisan about
"$PHP_BIN" artisan migrate:status
"$PHP_BIN" artisan route:list
"$PHP_BIN" artisan schedule:list
test -f public/build/manifest.json && echo "Vite build manifest is present"
```

If a command fails, stop and resolve the error before continuing. Do not treat blank output as proof of success; check the command's exit code.

## 7. Create the first Super Admin and schedule recurring work

Create the administrator interactively:

```sh
"$PHP_BIN" artisan app:create-super-admin
```

Use a unique email and a strong password. Do not create a Super Admin through public investor registration.

In cPanel **Cron Jobs**, choose **Once Per Minute** in Common Settings (or set all five schedule fields to `*`). If the form has a separate Command field, enter only this command there; do not include the five cron schedule fields:

```sh
cd /home/CPANEL_USER/apps/worifinance && /usr/local/bin/ea-php85 artisan schedule:run >> /dev/null 2>&1
```

If the editor expects a complete crontab line, use:

```cron
* * * * * cd /home/CPANEL_USER/apps/worifinance && /usr/local/bin/ea-php85 artisan schedule:run >> /dev/null 2>&1
```

Confirm the PHP executable path with the host. Without this cron job, scheduled annual account charges will not run.

## 8. Verify the live site

1. Open `https://YOUR_DOMAIN`; confirm HTTPS, the home page, project catalogue, English/Bangla switch, and static assets load.
2. Sign in at `/admin/login`; review the platform fee, currency rate, withdrawal minimum, referral commission, and payment instructions.
3. Use **Update password** in Super Admin to set a unique production password.
4. Test password reset with an account you control; verify that the SMTP email arrives and its link uses HTTPS and the correct domain.
5. Upload and verify the Binance/USDT QR image. Independently verify the receiving wallet and network.
6. Confirm `artisan schedule:list` shows the annual-charge task and that the cPanel cron entry is active.

If email fails, check Laravel logs under `storage/logs`, verify SMTP settings with the host, and rebuild cached configuration:

```sh
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
```

## Updating an existing deployment

For each release:

1. Back up the production database.
2. Build `vendor-production.zip` and `public/build` locally from the same release commit, as described in step 2.
3. In cPanel, pull the code update:

   ```sh
   cd "$HOME/apps/worifinance"
   PHP_BIN=/usr/local/bin/ea-php85
   git status --short --branch
   git pull --ff-only origin main
   ```

4. Upload the matching `vendor-production.zip` into the private application directory. Rename the old `vendor` directory before extracting the new one; keep the old directory until verification succeeds.
5. Verify `vendor/autoload.php`, then run:

   ```sh
   cd "$HOME/apps/worifinance"
   test -f vendor/autoload.php || { echo "Missing dependencies; stop."; exit 1; }
   "$PHP_BIN" artisan optimize:clear
   "$PHP_BIN" artisan migrate --force
   "$PHP_BIN" artisan config:cache
   "$PHP_BIN" artisan route:cache
   "$PHP_BIN" artisan view:cache
   "$PHP_BIN" artisan about
   "$PHP_BIN" artisan migrate:status
   ```

6. If the domain is locked to `public_html`, copy updated public assets there while preserving its cPanel `.htaccess` settings and custom Laravel `index.php`.

Do not overwrite `.env`, regenerate `APP_KEY`, or run development seeders against production.

## Troubleshooting and operational safety

- **`force-cgi-redirect` browser message:** the web PHP handler is misconfigured. Ask the host to fix the PHP 8.5 handler; this occurs before Laravel executes.
- **`vendor/autoload.php` missing:** upload and extract `vendor-production.zip` into the private app directory, not `public_html`.
- **`artisan` exits with an error:** inspect the actual error and exit code. Do not proceed with migrations, admin creation, or caching until Artisan runs successfully.
- **HTTPS warning:** check AutoSSL / **SSL/TLS Status** for the domain and `www` hostname.
- **Broken `imap.so` warning:** IMAP is not required by the application. Ask the host to disable or repair the broken extension; password reset uses SMTP.
- Keep `APP_DEBUG=false`; only `storage` and `bootstrap/cache` need to be writable by PHP. Do not use `chmod 777`.
- Keep backups secure and test database restore procedures.
- Password reset requires working SMTP. Reset requests are designed not to reveal whether an account exists; tokens expire and are single-use.
- Deposit requests are not automatically verified against a payment gateway or blockchain. Super Admin must verify payments before approval.
- Investment requests reserve funds and units for review. Profit distributions and referral commissions require Super Admin action.
- Withdrawal payouts are external; verify and send funds before marking a request paid.
- Annual membership charges use available BDT funds. Insufficient balances remain due for retry and do not suspend accounts.
- Verify receiving wallet details, network, QR image, and payment instructions before publishing. Never put passwords or private keys in public payment instructions.

For application behavior and financial workflow details, see [README.md](./README.md).
