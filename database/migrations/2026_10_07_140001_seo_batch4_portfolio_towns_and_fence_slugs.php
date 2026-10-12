<?php

use App\Support\SeoBatch4PortfolioUpgrade;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SeoBatch4PortfolioUpgrade::apply();
    }

    public function down(): void
    {
        // Towns, titles, and the photo move are not reversed. Old fence
        // addresses stay in project_slug_redirects so public 301s keep working.
    }
};
