# Laravel Deployment Guide for Hostinger

## Important: Document Root Configuration

**CRITICAL:** Your Hostinger hosting document root MUST point to the `public` folder, NOT the root of your Laravel project.

### Steps to Fix on Hostinger:

1. **In Hostinger Control Panel:**
   - Go to **Websites** → **Your Domain** → **Settings**
   - Look for **"Document Root"** or **"Public HTML"** setting
   - Change it from `/public_html` or `/` to `/public_html/public`
   - OR if you uploaded files to a subdirectory, point it to that subdirectory's `public` folder

2. **Alternative: If you can't change document root:**
   - Upload ALL Laravel files to your hosting
   - The `.htaccess` file in the root will redirect to `public` folder
   - Make sure mod_rewrite is enabled on Hostinger

## File Upload Checklist

Upload these files/folders to your Hostinger hosting:

```
✅ app/
✅ bootstrap/
✅ config/
✅ database/ (if you have migrations)
✅ public/ (THIS IS CRITICAL - contains index.php)
✅ resources/
✅ routes/
✅ storage/ (make sure it's writable)
✅ vendor/ (or run composer install on server)
✅ .env (create this on server, don't upload from local)
✅ .htaccess (both root and public/)
✅ artisan
✅ composer.json
✅ composer.lock (if exists)
```

## Server Requirements

- **PHP Version:** 8.0 or higher (you have 8.2 selected - perfect!)
- **Required PHP Extensions:**
  - OpenSSL
  - PDO
  - Mbstring
  - Tokenizer
  - XML
  - Ctype
  - JSON
  - BCMath
  - Fileinfo

## Setup Steps on Hostinger

1. **Upload all files** via FTP/File Manager

2. **Set file permissions:**
   ```
   storage/ → 755 (or 775)
   storage/framework/ → 755
   storage/logs/ → 755
   bootstrap/cache/ → 755
   ```

3. **Create .env file on server:**
   - Copy `.env.example` to `.env`
   - Update these values:
     ```
     APP_ENV=production
     APP_DEBUG=false
     APP_URL=https://zaninirenovations.ca
     ```

4. **Generate application key:**
   - Via SSH: `php artisan key:generate`
   - Or via Hostinger terminal/SSH access

5. **Install dependencies:**
   - Via SSH: `composer install --no-dev --optimize-autoloader`
   - Or upload the `vendor` folder from your local project

6. **Clear and cache config:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

## Common Issues

### HTTP 500 Error:
- Check file permissions (storage/ and bootstrap/cache/ must be writable)
- Check `.env` file exists and has APP_KEY set
- Check error logs in `storage/logs/laravel.log`
- Verify document root points to `public` folder

### Page not found:
- Document root is not pointing to `public` folder
- `.htaccess` file is missing or mod_rewrite is disabled

### White screen:
- Check `storage/logs/laravel.log` for errors
- Verify all files uploaded correctly
- Check PHP version matches requirements

## Quick Test

After deployment, test:
- `https://zaninirenovations.ca` - Should show welcome page
- `https://zaninirenovations.ca/public` - Should NOT work (if it does, document root is wrong)

