# Quick Fix for Hostinger HTTP 500 Error

## Most Common Issue: Document Root

**The problem:** Hostinger's document root is probably pointing to `/public_html` but Laravel needs it to point to `/public_html/public`

## Solution Options:

### Option 1: Change Document Root (RECOMMENDED)
1. Log into Hostinger Control Panel
2. Go to **Websites** → **zaninirenovations.ca** → **Settings** or **Advanced**
3. Look for **"Document Root"** or **"Public HTML Directory"**
4. Change from `/public_html` to `/public_html/public`
5. Save and wait 1-2 minutes

### Option 2: Use Root .htaccess (If you can't change document root)
I've created a `.htaccess` file in the root that redirects to `public` folder.
Make sure mod_rewrite is enabled in Hostinger PHP Configuration.

## Quick Checks:

1. **Test PHP is working:**
   - Visit: `https://zaninirenovations.ca/test.php`
   - Should show PHP info page
   - If you see the PHP info, PHP is working!

2. **Check file structure on server:**
   Your files should be uploaded like this:
   ```
   /public_html/
     ├── app/
     ├── bootstrap/
     ├── config/
     ├── public/
     │   ├── index.php  ← This is the entry point
     │   ├── .htaccess
     │   ├── css/
     │   └── images/
     ├── resources/
     ├── routes/
     ├── storage/
     ├── vendor/
     └── .htaccess  ← Root redirect file
   ```

3. **Verify .env file exists:**
   - On server, make sure `.env` file exists in root
   - It should have `APP_KEY=base64:...` set
   - If missing, copy `.env.example` to `.env` and run `php artisan key:generate`

4. **Check file permissions:**
   - `storage/` folder: 755 or 775
   - `bootstrap/cache/` folder: 755 or 775

## If Still Getting 500 Error:

1. **Check error logs:**
   - In Hostinger: **Advanced** → **Error Log**
   - Or check: `storage/logs/laravel.log` via File Manager

2. **Enable debug mode temporarily:**
   - In `.env` file on server, set:
     ```
     APP_DEBUG=true
     ```
   - This will show detailed error messages
   - **REMEMBER TO SET BACK TO `false` AFTER FIXING!**

3. **Verify PHP extensions:**
   - In Hostinger: **Advanced** → **PHP Configuration** → **PHP extensions**
   - Enable: OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath, Fileinfo

## After Fixing:

1. Clear Laravel cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```

2. Test the site:
   - Visit: `https://zaninirenovations.ca`
   - Should show your welcome page!

3. Remove test file:
   - Delete `public/test.php` for security

