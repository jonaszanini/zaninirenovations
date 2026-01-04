# Zanini Renovations

Professional Renovation and Carpentry Services Website

## About

Zanini Renovations is a Laravel-based website for advertising renovation and carpentry work. The site will include authentication for admin, helpers, and clients.

## Quick Start

### Prerequisites
- PHP 8.1 or higher
- Composer

### Installation Steps

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Set up environment file:**
   ```bash
   copy .env.example .env
   ```
   (On Windows PowerShell, use `copy` instead of `cp`)

3. **Generate application key:**
   ```bash
   php artisan key:generate
   ```

4. **Start the development server:**
   ```bash
   php artisan serve
   ```

5. **Open your browser:**
   Navigate to `http://localhost:8000` to see the welcome page.

### Optional: Database Setup

If you plan to use a database later, configure it in `.env`:
- `DB_CONNECTION=mysql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_DATABASE=zanini_renovations`
- `DB_USERNAME=root`
- `DB_PASSWORD=`

Then run migrations when ready:
```bash
php artisan migrate
```

## Development

The application is currently showing a "Coming Soon" welcome page. Future features will include:
- Authentication system for admin, helpers, and clients
- Project management
- Client portal

## Color Palette

- Main Color: `#024768` (Dark Teal/Blue)
- Dark Color/Font: `#373435` (Dark Gray)
- Secondary Color: `#3a4856` (Slate Gray)
- Accent Color: `#9dadbc` (Light Blue Gray)

## License

MIT

