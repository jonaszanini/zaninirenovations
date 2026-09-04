<?php

namespace App\Mail;

use App\Models\ClientProjectHelperPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Support\SignedPublicLink;
use Illuminate\Support\Facades\URL;

class HelperPaymentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public ClientProjectHelperPayment $payment;

    public string $acknowledgeUrl;

    public function __construct(ClientProjectHelperPayment $payment)
    {
        $this->payment = $payment;
        $this->acknowledgeUrl = URL::temporarySignedRoute(
            'public.helper-payment.acknowledge',
            SignedPublicLink::expiresAt(),
            ['helper_payment' => $payment->id],
        );
    }

    public function build(): self
    {
        $project = $this->payment->project;
        $helper = $this->payment->helper;
        $siteName = (string) config('mail.from.name', 'Zanini Renovations');

        $subject = 'Payment for your work on '.$project->name.' — '.$siteName;

        return $this
            ->subject($subject)
            ->replyTo(config('mail.from.address'), $siteName)
            ->view('emails.helpers.payment_notification', [
                'payment' => $this->payment,
                'project' => $project,
                'helper' => $helper,
                'acknowledgeUrl' => $this->acknowledgeUrl,
                'siteName' => $siteName,
            ]);
    }
}
