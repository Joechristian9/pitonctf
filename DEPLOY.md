# Deploying PitonCTF

Sized for one class: about 40 students in teams of 4 and a few admins. Every open challenges or scoreboard page polls every 5 seconds, so expect about 8 requests per second during a game. One server with PHP and MySQL is enough.

## Where to run it

- **Hostinger shared hosting** (PHP + MySQL, LiteSpeed): the upload and document-root steps are in [README_CTF.md, "Deploy to Hostinger"](README_CTF.md#deploy-to-hostinger-shared-hosting). Use those steps, plus the settings below.
- **A classroom PC with XAMPP:** serve `public/` through XAMPP's **Apache**, not `php artisan serve`. On Windows `artisan serve` handles one request at a time, which 8 requests per second will queue up.

## Production `.env`

On top of the README list:

```
APP_ENV=production
APP_DEBUG=false                 # never true on a public server: it shows code, queries and secrets
APP_URL=https://your-domain     # passkeys only work on this exact origin
SESSION_SECURE_COOKIE=true      # once the site is on HTTPS
LOG_CHANNEL=stack
LOG_STACK=daily                 # one file per day, kept 14 days
LOG_LEVEL=warning
QUEUE_CONNECTION=sync           # nothing in the app needs a queue worker or the scheduler
```

## First deploy only

Run these once, on an empty database, before the steps below:

```
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
```

Never run them again on a live site:

- `key:generate` replaces the app key. Everyone is logged out, and admins with two-factor login are locked out, because their 2FA secrets were encrypted with the old key.
- `db:seed` resets the built-in challenges to `database/data/challenges.json`, undoing any flag, points or hint an admin changed in the app. To change the instructor account later, use **Settings → Profile** in the app instead.

## Steps, every deploy

1. On your PC: `npm run build`, then upload (see README). `vendor/`: `composer install --no-dev --optimize-autoloader`.
2. Back up first (see below) if a game has already been played.
3. In the project folder:
    ```
    php artisan down
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    php artisan permission:cache-reset
    php artisan up
    ```
4. Writable by the web server: `storage/`, `bootstrap/cache/` and `public/ctf/uploads/`.
5. PHP: keep **OPcache** on (`opcache.enable=1`, the default on Hostinger and XAMPP).
6. Check `https://your-domain/up` returns 200. Point an uptime monitor at it if you want a warning when the site goes down.

## What the app sets for you

- Security headers (`nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, and HSTS on HTTPS) come from `App\Http\Middleware\AddSecurityHeaders`.
- `public/.htaccess` lets browsers cache `public/build/assets/*` for a year (the file names change on every build). Apache and LiteSpeed read it; on nginx add the same `Cache-Control` for `/build/assets/`.

## Backups

Two things hold data that cannot be rebuilt: the MySQL database and the uploaded files.

- **Database**, before every deploy and after each class: hPanel → Databases → Backups, or `mysqldump -u USER -p DB_NAME > pitonctf-YYYY-MM-DD.sql`. Restore with `mysql -u USER -p DB_NAME < file.sql`.
- **Files**: `storage/app/private/writeups/` (team write-ups) and `public/ctf/uploads/` (challenge files added in the admin panel). Download both before redeploying over the folder.

## Logs

Errors go to `storage/logs/laravel-YYYY-MM-DD.log`. Look there first when someone reports a problem.
