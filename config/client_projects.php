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

    // Self-addressed admin reminder/notification emails are DISABLED by default
    // (empty recipient = no email sent). To re-enable any of them, set the
    // matching env var to an email address. Note: the day-before-commencement
    // SMS to the *client* is unaffected by these — it's controlled by Mocean.

    // Email address that receives the daily Quote Pending reminders.
    'quote_reminder_email_to' => env('CLIENT_PROJECTS_REMINDER_TO', ''),

    // Email address that receives the 2-day Quote Sent follow-up reminder.
    'quote_follow_up_email_to' => env('CLIENT_PROJECTS_FOLLOW_UP_TO', ''),

    // Email address that receives the "Add to Google Calendar" link when
    // start/end dates are saved on a project.
    'calendar_email_to' => env('CLIENT_PROJECTS_CALENDAR_TO', ''),

    // Admin email for the automated "day before work begins" notice (runs via scheduler).
    'commencement_eve_email_to' => env('CLIENT_PROJECTS_COMMENCEMENT_EVE_TO', ''),

    // Daily past-due invoice payment reminders to clients (email preferred, else SMS).
    'past_due_invoice_reminders_enabled' => filter_var(
        env('CLIENT_PROJECTS_PAST_DUE_INVOICE_REMINDERS', true),
        FILTER_VALIDATE_BOOLEAN
    ),

    // Google review URL used in the thank-you email to the client.
    'review_url' => env(
        'CLIENT_PROJECTS_REVIEW_URL',
        'https://g.page/r/CdqOL8mPAolqEBM/review'
    ),

    // Canadian HST rate used in profit calculations.
    'hst_rate' => 0.13,

    /*
    | GST/HST registration number (15 characters, CRA format: 123456789RT0001).
    | Shown on invoice PDFs so clients can claim Input Tax Credits (ITCs).
    */
    'gst_hst_registration_number' => env('GST_HST_REGISTRATION_NUMBER', '709216154RT0001'),
];
