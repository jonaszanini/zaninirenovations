# Fix for Hostinger Index File Issue

## The Problem
Hostinger is serving `index.html` instead of Laravel's `index.php`, which causes the HTTP 500 error because Laravel needs `index.php` to run.

## Solution Steps:

### Step 1: Check Your File Structure on Hostinger

Log into Hostinger File Manager and verify your files are uploaded like this:

```
/public_html/                    ← Document root
  ├── app/
  ├── bootstrap/
  ├── config/
  ├── public/                    ← Laravel's public folder
  │   ├── index.php             ← Laravel entry point
  │   ├── .htaccess
  │   ├── css/
  │   └── images/
  ├── resources/
  ├── routes/
  ├── storage/
  ├── vendor/
  └── .htaccess
```

### Step 2: Remove index.html (If It Exists)

1. In Hostinger File Manager, go to `/public_html/`
2. Look for `index.html` file
3. **DELETE it** or rename it to `index.html.old`
4. This will allow `index.php` to be served

### Step 3: Change Document Root to Public Folder

**THIS IS THE MOST IMPORTANT STEP:**

1. In Hostinger Control Panel:
   - Go to **Websites** → **zaninirenovations.ca** → **Settings**
   - Find **"Document Root"** or **"Public HTML Directory"**
   - Change from `/public_html` to `/public_html/public`
   - Save changes

2. Wait 1-2 minutes for changes to take effect

### Step 4: Verify Setup

After making changes:

1. Visit: `https://zaninirenovations.ca`
2. Should show your Laravel welcome page
3. If still not working, check:
   - Error logs in Hostinger
   - `.env` file exists and has `APP_KEY` set
   - File permissions on `storage/` folder (should be 755)

## Alternative: If You Can't Change Document Root

If Hostinger won't let you change the document root, you have two options:

### Option A: Move Files Up One Level
Move everything from `public/` folder directly into `public_html/`:
- This means `index.php` goes directly in `/public_html/`
- Update paths in `index.php` from `__DIR__.'/../` to `__DIR__.'/../` (but this is messy)

### Option B: Use Root .htaccess Redirect
The `.htaccess` file I created in the root should redirect to `public` folder, but you need:
- mod_rewrite enabled
- Document root still pointing to `/public_html` (not `/public_html/public`)

## Recommended Solution

**Change the document root to `/public_html/public`** - This is the cleanest and most secure way to deploy Laravel.

