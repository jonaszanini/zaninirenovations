<?php

/**
 * Legacy entry point for the theme AJAX URL (custom-scripts.js fallback).
 * Uses the same mail pipeline as POST /contact-email (Laravel).
 */

header('Content-Type: application/json; charset=utf-8');

$basePath = dirname(__DIR__);

if (! is_file($basePath.'/vendor/autoload.php')) {
    echo json_encode(['status' => 0, 'msg' => 'Application is not installed.']);
    exit;
}

require $basePath.'/vendor/autoload.php';

$app = require_once $basePath.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$result = \App\Services\ContactFormMailer::handle($_POST);

echo json_encode($result);
