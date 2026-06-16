# Final Setup Instructions for Hostinger

## Important: Hostinger Doesn't Allow Document Root Changes

Since Hostinger's document root is fixed at `/public_html` and cannot be changed, we need to use an `.htaccess` redirect.

## File Structure on Hostinger

Upload your files so they look like this:

```
/public_html/                    ← Document root (fixed by Hostinger)
  ├── .htaccess                 ← Root redirect file (IMPORTANT!)
  ├── app/
  ├── bootstrap/
  ├── config/
  ├── public/                   ← Laravel's public folder
  │   ├── index.php            ← Laravel entry point
  │   ├── .htaccess
  │   ├── css/
  │   ├── images/
  │   └── test.php
  ├── resources/
  ├── routes/
  ├── storage/
  ├── vendor/
  ├── .env                      ← Create this on server
  └── composer.json
```

## Setup Steps:

### Step 1: Upload All Files
Upload your entire Laravel project to `/public_html/` on Hostinger.

### Step 2: Verify .htaccess Files
Make sure BOTH `.htaccess` files are uploaded:
- `/public_html/.htaccess` (root - redirects to public)
- `/public_html/public/.htaccess` (Laravel routing)

### Step 3: Remove index.html (If Exists)
1. In Hostinger File Manager, go to `/public_html/`
2. If you see `index.html`, **DELETE it**
3. This ensures `index.php` takes priority

### Step 4: Create .env File on Server
1. Copy `.env.example` to `.env` in `/public_html/`
2. Update these values:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://zaninirenovations.ca
   ```
3. Generate application key via SSH or Hostinger terminal:
   ```bash
   php artisan key:generate
   ```

### Step 5: Set File Permissions
In Hostinger File Manager, set these permissions:
- `storage/` → 755 or 775
- `storage/framework/` → 755
- `storage/logs/` → 755
- `bootstrap/cache/` → 755

### Step 6: Install Dependencies (If Needed)
If you didn't upload the `vendor/` folder:
- Use Hostinger SSH/Terminal:
  ```bash
  cd /public_html
  composer install --no-dev --optimize-autoloader
  ```

### Step 7: Test
1. Visit: `https://zaninirenovations.ca`
2. Should redirect to `/public/` and show your Laravel welcome page
3. Test PHP: `https://zaninirenovations.ca/test.php` (delete after testing)

## How the Redirect Works

The root `.htaccess` file will:
- Redirect all requests from `/public_html/` to `/public_html/public/`
- Laravel's `index.php` in the `public/` folder will handle the request
- The `public/.htaccess` handles Laravel's routing

## Troubleshooting

### Still Getting 500 Error?
1. Check error logs: Hostinger → Advanced → Error Log
2. Enable debug temporarily in `.env`: `APP_DEBUG=true`
3. Check `storage/logs/laravel.log` via File Manager
4. Verify mod_rewrite is enabled (should be by default)

### Page Not Found?
- Check that both `.htaccess` files exist
- Verify `public/index.php` exists
- Check file permissions on `storage/` folder

### White Screen?
- Check `.env` file exists and has `APP_KEY` set
- Verify all files uploaded correctly
- Check PHP version is 8.0+ (you have 8.2 - perfect!)

## After Everything Works

1. Clear Laravel cache:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

2. Set `APP_DEBUG=false` in `.env` for security

3. Delete `public/test.php` file

4. **Set up daily automations (cron-job.org)** — hPanel cron does not work on
   this host. In `.env` set `CRON_HTTP_TOKEN` to a long random secret. On
   [cron-job.org](https://cron-job.org), create a job:
   - URL: `https://zaninirenovations.ca/cron/run?token=YOUR_TOKEN`
   - Schedule: daily at 9:00 AM (Eastern / America/Toronto)
   - Method: GET

   A successful hit returns plain text like `automations:run-daily completed`.

5. Your site should now be live!



