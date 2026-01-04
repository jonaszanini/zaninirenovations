# Files to Upload to Hostinger - Checklist

## ✅ UPLOAD THESE (Required):

### Folders:
- ✅ `app/` - Application code
- ✅ `bootstrap/` - Bootstrap files
- ✅ `config/` - Configuration files
- ✅ `public/` - **CRITICAL** - Contains index.php and assets
- ✅ `resources/` - Views and assets
- ✅ `routes/` - Route definitions
- ✅ `storage/` - Storage directory (must be writable)
- ✅ `vendor/` - Composer dependencies (if you have it locally)

### Files:
- ✅ `.htaccess` - Root redirect file (IMPORTANT!)
- ✅ `artisan` - Laravel command-line tool
- ✅ `composer.json` - Dependencies definition
- ✅ `composer.lock` - Locked dependencies (if exists)

## ❌ DON'T UPLOAD THESE (Skip):

- ❌ `.env` - **Create fresh on server** (see below)
- ❌ `.gitignore` - Not needed on server
- ❌ `colors` - Not needed
- ❌ `tests/` - Not needed for production
- ❌ `phpunit.xml` - Testing config, not needed
- ❌ `README.md` - Documentation, not needed
- ❌ `DEPLOYMENT.md` - Documentation, not needed
- ❌ `HOSTINGER_*.md` - Documentation files, not needed

## ⚠️ IMPORTANT: Create on Server

### `.env` file:
1. Copy `.env.example` to `.env` on the server
2. Update these values:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://zaninirenovations.ca
   ```
3. Generate APP_KEY:
   - Via SSH: `php artisan key:generate`
   - Or manually set a key

## 📋 Quick Upload Steps:

1. **Select all folders** (app, bootstrap, config, public, resources, routes, storage)
2. **Select these files**: `.htaccess`, `artisan`, `composer.json`, `composer.lock`
3. **Upload to** `/public_html/` on Hostinger
4. **If vendor folder exists locally**, upload it too
5. **If vendor doesn't exist**, install via SSH: `composer install --no-dev --optimize-autoloader`
6. **Create `.env` file** on server (don't upload your local one)
7. **Set permissions**: `storage/` → 755, `bootstrap/cache/` → 755

## File Structure After Upload:

```
/public_html/
  ├── .htaccess          ← Root redirect
  ├── app/
  ├── bootstrap/
  ├── config/
  ├── public/            ← Contains index.php
  │   ├── .htaccess
  │   ├── index.php
  │   ├── css/
  │   └── images/
  ├── resources/
  ├── routes/
  ├── storage/           ← Must be writable (755)
  ├── vendor/            ← Or install via composer
  ├── artisan
  ├── composer.json
  └── .env               ← Create this on server
```



