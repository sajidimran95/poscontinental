# On-Premises Hosting (Continental Wholesale)

The POS runs on a local server at the customer site (not cloud-hosted).

## Recommended stack

- Windows Server or Linux with PHP 8.3+, MySQL/MariaDB, Nginx/Apache or Laragon
- Node.js only for asset builds (`npm run build`); production serves `public/`
- Scheduler: `php artisan schedule:run` every minute
- Queue (optional): `php artisan queue:work`

## Deploy updates

1. Pull/copy release to server
2. `composer install --no-dev -o`
3. `php artisan migrate --force`
4. `npm ci && npm run build` (or copy prebuilt `public/build`)
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. Restart PHP-FPM / queue workers

## Backups

- Nightly MySQL dump + `storage/app` (images/uploads)
- Keep one offsite copy (USB/NAS/cloud bucket)

## Remote access for mobile apps

Options (pick with customer):

1. **Site-to-site / user VPN** — apps connect over VPN to internal hostname
2. **Reverse proxy** — Nginx + TLS (Let's Encrypt or internal CA) to `public/` only; firewall allowlist if possible
3. **Relay** — small cloud reverse tunnel in front of on-prem (last resort)

Never expose MySQL publicly. API uses Sanctum bearer tokens over HTTPS only.

## Accessibility

Before go-live: keyboard pass on all Sales/Inventory/Purchasing screens; JAWS/VoiceOver audit on login, SO form, invoices modal, and lookups.
