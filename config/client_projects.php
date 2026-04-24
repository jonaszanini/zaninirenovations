<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Client Projects — business configuration
    |--------------------------------------------------------------------------
    | These values drive the Projects management system in the admin area.
    | Override any of them with env vars if you need to test with a different
    | recipient without editing code.
    */

    // Email address that receives the daily Quote Pending reminders.
    'quote_reminder_email_to' => env('CLIENT_PROJECTS_REMINDER_TO', 'zaninirenovations@gmail.com'),

    // Email address that receives the 2-day Quote Sent follow-up reminder.
    'quote_follow_up_email_to' => env('CLIENT_PROJECTS_FOLLOW_UP_TO', 'zaninirenovations@gmail.com'),

    // Email address that receives the "Add to Google Calendar" link when
    // start/end dates are saved on a project.
    'calendar_email_to' => env('CLIENT_PROJECTS_CALENDAR_TO', 'zaninirenovations@gmail.com'),

    // Admin email for the automated "day before work begins" notice (runs via scheduler).
    'commencement_eve_email_to' => env('CLIENT_PROJECTS_COMMENCEMENT_EVE_TO', 'zaninirenovations@gmail.com'),

    // Google review URL used in the thank-you email to the client.
    'review_url' => env(
        'CLIENT_PROJECTS_REVIEW_URL',
        'https://g.page/r/CdqOL8mPAolqEBM/review'
    ),

    // Canadian HST rate used in profit calculations.
    'hst_rate' => 0.13,
];
