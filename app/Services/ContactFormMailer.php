<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ContactFormMailer
{
    /**
     * Validate contact POST data and send notification email.
     *
     * @param  array<string, mixed>  $input
     * @return array{status: int, msg: string}
     */
    public static function handle(array $input): array
    {
        if (($input['safety_key'] ?? '') !== 'dynatf') {
            return ['status' => 0, 'msg' => 'Invalid request.'];
        }

        $extended = array_key_exists('location_city', $input) || array_key_exists('starting_date', $input);

        if (array_key_exists('starting_date', $input)) {
            $sd = $input['starting_date'];
            if ($sd === null || $sd === '' || (is_string($sd) && trim($sd) === '')) {
                $input['starting_date'] = null;
            }
        }

        $rules = [
            'user_name' => 'required|string|max:255',
            'user_email' => 'required|email|max:255',
            'email_subject' => 'required|string|max:200',
            'email_message' => 'required|string|max:10000',
            'budget' => 'nullable|string|max:500',
        ];

        if ($extended) {
            $rules['location_city'] = 'required|string|max:255';
            $rules['starting_date'] = 'nullable|date_format:Y-m-d';
        }

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            return ['status' => 0, 'msg' => 'Please fill in all fields.'];
        }

        $data = $validator->validated();

        $detailHtml = self::submissionDetailHtml($data, $extended);

        $to = config('mail.contact_to');
        if (! is_string($to) || $to === '') {
            Log::error('Contact form: MAIL_CONTACT_TO / MAIL_FROM_ADDRESS not configured.');

            return ['status' => 0, 'msg' => 'Sorry, mail is not configured. Please try again later.'];
        }

        try {
            Mail::html($detailHtml, function ($message) use ($to, $data) {
                $message->to($to)
                    ->replyTo($data['user_email'], $data['user_name'])
                    ->subject('[Website contact] '.$data['email_subject']);
            });
        } catch (\Throwable $e) {
            Log::error('Contact form mail failed', [
                'message' => $e->getMessage(),
            ]);

            return ['status' => 0, 'msg' => 'Sorry, we could not send your message. Please try again later.'];
        }

        self::sendAutoReply($data, $detailHtml);

        return [
            'status' => 1,
            'msg' => 'Thank you! Your message has been received. We will get back to you soon.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function submissionDetailHtml(array $data, bool $extended): string
    {
        $extra = '';
        if ($extended) {
            $extra = '<p><strong>Location / city:</strong> '.e($data['location_city']).'</p>';
            $start = $data['starting_date'] ?? '';
            if ($start !== '' && $start !== null) {
                $extra .= '<p><strong>Starting date:</strong> '.e((string) $start).'</p>';
            }
        }

        $budgetVal = trim((string) ($data['budget'] ?? ''));
        if ($budgetVal !== '') {
            $extra .= '<p><strong>Budget:</strong> '.e($budgetVal).'</p>';
        }

        return sprintf(
            '<p><strong>Name:</strong> %s</p><p><strong>Email:</strong> %s</p>%s<p><strong>Subject:</strong> %s</p><p><strong>Message:</strong></p><p>%s</p>',
            e($data['user_name']),
            e($data['user_email']),
            $extra,
            e($data['email_subject']),
            nl2br(e($data['email_message']))
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function sendAutoReply(array $data, string $detailHtml): void
    {
        $siteName = (string) config('mail.from.name', 'Zanini Renovations');
        $intro = sprintf(
            '<p>Hi %s,</p><p>Thank you for contacting %s. We have received your message and will get in touch as soon as possible.</p><p>Below is a copy of what you submitted for your records.</p><hr style="border:none;border-top:1px solid #ddd;margin:1.25em 0;">',
            e($data['user_name']),
            e($siteName)
        );

        $html = $intro.$detailHtml;

        try {
            Mail::html($html, function ($message) use ($data, $siteName) {
                $message->to($data['user_email'], $data['user_name'])
                    ->subject('Thank you — '.$siteName);
            });
        } catch (\Throwable $e) {
            Log::warning('Contact form auto-reply to visitor failed', [
                'visitor_email' => $data['user_email'],
                'message' => $e->getMessage(),
            ]);
        }
    }
}
